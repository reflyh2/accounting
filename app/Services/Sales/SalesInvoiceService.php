<?php

namespace App\Services\Sales;

use App\Domain\Accounting\DTO\AccountingEntry;
use App\Domain\Accounting\DTO\AccountingEventPayload;
use App\Enums\AccountingEventCode;
use App\Enums\Documents\InvoiceStatus;
use App\Enums\Documents\SalesOrderStatus;
use App\Exceptions\SalesInvoiceException;
use App\Models\SalesDeliveryLine;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Services\Accounting\AccountingEventBus;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class SalesInvoiceService
{
    private const QTY_TOLERANCE = 0.0005;
    private const COST_SCALE = 6;

    public function __construct(
        private readonly AccountingEventBus $accountingEventBus,
    ) {
    }

    public function create(array $payload, ?Authenticatable $actor = null): SalesInvoice
    {
        $actor ??= Auth::user();

        return DB::transaction(function () use ($payload, $actor) {
            /** @var SalesOrder $salesOrder */
            $salesOrder = SalesOrder::with(['branch.branchGroup', 'currency', 'partner'])
                ->findOrFail($payload['sales_order_id']);

            $this->assertSalesOrderInvoiceable($salesOrder);

            $invoiceDate = Carbon::parse($payload['invoice_date']);
            $exchangeRate = (float) ($payload['exchange_rate'] ?? $salesOrder->exchange_rate ?? 1);
            $preparedLines = $this->prepareLines(
                $salesOrder,
                $payload['lines'] ?? [],
                $exchangeRate
            );

            if (empty($preparedLines)) {
                throw new SalesInvoiceException('Minimal satu baris faktur wajib diisi.');
            }

            $invoice = SalesInvoice::create([
                'sales_order_id' => $salesOrder->id,
                'company_id' => $salesOrder->company_id,
                'branch_id' => $salesOrder->branch_id,
                'partner_id' => $salesOrder->partner_id,
                'currency_id' => $salesOrder->currency_id,
                'invoice_number' => $this->generateInvoiceNumber(
                    $salesOrder->company_id,
                    $salesOrder->branch_id,
                    $invoiceDate
                ),
                'invoice_date' => $invoiceDate,
                'due_date' => isset($payload['due_date']) ? Carbon::parse($payload['due_date']) : null,
                'customer_invoice_number' => $payload['customer_invoice_number'] ?? null,
                'exchange_rate' => $exchangeRate,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $actor?->getAuthIdentifier(),
            ]);

            $totals = $this->persistInvoiceLines($invoice, $preparedLines);

            $invoice->update([
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total_amount' => $totals['total_amount'],
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            return $invoice->fresh([
                'salesOrder.partner',
                'salesOrder.branch',
                'lines.salesOrderLine',
                'lines.salesDeliveryLine.salesDelivery',
                'currency',
            ]);
        });
    }

    public function update(SalesInvoice $invoice, array $payload, ?Authenticatable $actor = null): SalesInvoice
    {
        $this->assertDraft($invoice);

        if ((int) $invoice->sales_order_id !== (int) $payload['sales_order_id']) {
            throw new SalesInvoiceException('Sales Order tidak dapat diubah setelah faktur dibuat.');
        }

        $actor ??= Auth::user();

        return DB::transaction(function () use ($invoice, $payload, $actor) {
            $invoice->load('salesOrder.branch.branchGroup', 'salesOrder.currency');

            $salesOrder = $invoice->salesOrder;

            $invoiceDate = Carbon::parse($payload['invoice_date']);
            $exchangeRate = (float) ($payload['exchange_rate'] ?? $invoice->exchange_rate ?? 1);

            $preparedLines = $this->prepareLines(
                $salesOrder,
                $payload['lines'] ?? [],
                $exchangeRate
            );

            if (empty($preparedLines)) {
                throw new SalesInvoiceException('Minimal satu baris faktur wajib diisi.');
            }

            $invoice->lines()->delete();
            $totals = $this->persistInvoiceLines($invoice, $preparedLines);

            $invoice->update([
                'invoice_date' => $invoiceDate,
                'due_date' => isset($payload['due_date']) ? Carbon::parse($payload['due_date']) : null,
                'customer_invoice_number' => $payload['customer_invoice_number'] ?? null,
                'exchange_rate' => $exchangeRate,
                'notes' => $payload['notes'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total_amount' => $totals['total_amount'],
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            return $invoice->fresh([
                'salesOrder.partner',
                'lines.salesOrderLine',
                'lines.salesDeliveryLine.salesDelivery',
            ]);
        });
    }

    public function delete(SalesInvoice $invoice): void
    {
        $this->assertDraft($invoice);

        DB::transaction(function () use ($invoice) {
            $invoice->lines()->delete();
            $invoice->delete();
        });
    }

    public function post(SalesInvoice $invoice, ?Authenticatable $actor = null): SalesInvoice
    {
        $this->assertDraft($invoice);
        $actor ??= Auth::user();

        return DB::transaction(function () use ($invoice, $actor) {
            $invoice->load([
                'salesOrder.branch.branchGroup.company',
                'salesOrder.lines',
                'lines.salesOrderLine',
                'lines.salesDeliveryLine.salesDelivery',
                'currency',
            ]);

            $prepared = $this->preparePostingLines($invoice);

            $soLineIds = collect($prepared)->pluck('sales_order_line_id')->unique()->values();
            $sdLineIds = collect($prepared)->pluck('sales_delivery_line_id')->filter()->unique()->values();

            /** @var Collection<int, SalesOrderLine> $lockedSoLines */
            $lockedSoLines = SalesOrderLine::whereIn('id', $soLineIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Collection<int, SalesDeliveryLine> $lockedSdLines */
            $lockedSdLines = SalesDeliveryLine::whereIn('id', $sdLineIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($prepared as $line) {
                /** @var SalesOrderLine $soLine */
                $soLine = $lockedSoLines->get($line['sales_order_line_id']);
                if (!$soLine) {
                    throw new SalesInvoiceException('Baris SO tidak ditemukan saat posting faktur.');
                }

                $this->assertLineStillAvailable(
                    $line,
                    $soLine,
                    $lockedSdLines->get($line['sales_delivery_line_id'])
                );

                $soLine->quantity_invoiced = $this->roundQuantity(
                    (float) $soLine->quantity_invoiced + $line['quantity']
                );
                $soLine->quantity_invoiced_base = $this->roundQuantity(
                    (float) $soLine->quantity_invoiced_base + $line['quantity_base']
                );
                $soLine->amount_invoiced = $this->roundMoney(
                    (float) $soLine->amount_invoiced + $line['line_total']
                );
                $soLine->save();

                if ($line['sales_delivery_line_id']) {
                    /** @var SalesDeliveryLine $sdLine */
                    $sdLine = $lockedSdLines->get($line['sales_delivery_line_id']);
                    if ($sdLine) {
                        $sdLine->quantity_invoiced = $this->roundQuantity(
                            (float) $sdLine->quantity_invoiced + $line['quantity']
                        );
                        $sdLine->quantity_invoiced_base = $this->roundQuantity(
                            (float) $sdLine->quantity_invoiced_base + $line['quantity_base']
                        );
                        $sdLine->amount_invoiced = $this->roundMoney(
                            (float) $sdLine->amount_invoiced + $line['line_total']
                        );
                        $sdLine->save();
                    }
                }

                SalesInvoiceLine::whereKey($line['line_id'])
                    ->update([
                        'quantity_base' => $line['quantity_base'],
                        'line_total_base' => $line['line_total_base'],
                        'delivery_value_base' => $line['delivery_value_base'],
                        'revenue_variance' => $line['revenue_variance'],
                        'tax_amount' => $line['tax_amount'],
                    ]);
            }

            $totals = $this->calculateTotals($prepared);

            $invoice->update([
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total_amount' => $totals['total_amount'],
                'delivery_value_base' => $totals['delivery_value_base'],
                'revenue_variance' => $totals['revenue_variance'],
                'status' => InvoiceStatus::POSTED->value,
                'posted_at' => now(),
                'posted_by' => $actor?->getAuthIdentifier(),
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            $invoice->salesOrder->refresh();
            $invoice->salesOrder->loadMissing('lines');

            $this->syncSalesOrderBillingStatus($invoice->salesOrder, $actor);
            $this->dispatchArPostedEvent($invoice->fresh('currency'), $totals, $actor);

            return $invoice->fresh([
                'salesOrder.partner',
                'lines.salesOrderLine',
                'lines.salesDeliveryLine.salesDelivery',
            ]);
        });
    }

    private function prepareLines(
        SalesOrder $salesOrder,
        array $lines,
        float $exchangeRate
    ): array {
        if (empty($lines)) {
            return [];
        }

        $soLines = $salesOrder->lines()
            ->with(['uom', 'baseUom'])
            ->get()
            ->keyBy('id');

        $deliveryLines = SalesDeliveryLine::query()
            ->with('salesDelivery')
            ->whereIn('sales_order_line_id', $soLines->keys())
            ->get()
            ->keyBy('id');

        $prepared = [];
        $lineNumber = 1;

        foreach ($lines as $payloadLine) {
            $salesOrderLineId = (int) ($payloadLine['sales_order_line_id'] ?? 0);
            $salesDeliveryLineId = (int) ($payloadLine['sales_delivery_line_id'] ?? 0);
            $quantity = (float) ($payloadLine['quantity'] ?? 0);
            $unitPrice = (float) ($payloadLine['unit_price'] ?? 0);
            $taxAmount = (float) ($payloadLine['tax_amount'] ?? 0);

            if ($quantity <= 0) {
                throw new SalesInvoiceException('Jumlah faktur harus lebih dari nol.');
            }

            if ($unitPrice < 0) {
                throw new SalesInvoiceException('Harga satuan tidak boleh bernilai negatif.');
            }

            /** @var SalesOrderLine|null $soLine */
            $soLine = $soLines->get($salesOrderLineId);
            if (!$soLine) {
                throw new SalesInvoiceException('Baris Sales Order tidak ditemukan.');
            }

            if (!$salesDeliveryLineId) {
                throw new SalesInvoiceException('Baris pengiriman harus dipilih.');
            }

            /** @var SalesDeliveryLine|null $deliveryLine */
            $deliveryLine = $deliveryLines->get($salesDeliveryLineId);
            if (!$deliveryLine || (int) $deliveryLine->sales_order_line_id !== $soLine->id) {
                throw new SalesInvoiceException('Baris pengiriman tidak sah untuk SO tersebut.');
            }

            $availableQuantity = max(
                0.0,
                (float) $deliveryLine->quantity
                    - (float) $deliveryLine->quantity_invoiced
            );

            if (($quantity - $availableQuantity) > self::QTY_TOLERANCE) {
                throw new SalesInvoiceException('Jumlah faktur melebihi sisa pada delivery.');
            }

            $soAvailable = max(
                0.0,
                ((float) $soLine->quantity_delivered)
                    - (float) $soLine->quantity_invoiced
            );

            if (($quantity - $soAvailable) > self::QTY_TOLERANCE) {
                throw new SalesInvoiceException('Jumlah faktur melebihi sisa pengiriman SO.');
            }

            $quantityBase = $this->deriveBaseQuantity($quantity, $deliveryLine, $soLine);
            $lineTotal = $this->roundMoney($quantity * $unitPrice);
            $lineTotalBase = $this->roundCost($lineTotal * $exchangeRate);
            $deliveryValueBase = $this->roundCost($quantityBase * (float) $deliveryLine->unit_cost_base);
            $revenueVariance = $this->roundMoney(($lineTotalBase + ($taxAmount * $exchangeRate)) - $deliveryValueBase);

            $prepared[] = [
                'line_number' => $lineNumber++,
                'sales_order_line_id' => $soLine->id,
                'sales_delivery_line_id' => $deliveryLine->id,
                'description' => $payloadLine['description'] ?? $soLine->description,
                'uom_label' => $soLine->uom?->name,
                'quantity' => $this->roundQuantity($quantity),
                'quantity_base' => $quantityBase,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'line_total_base' => $lineTotalBase,
                'delivery_value_base' => $deliveryValueBase,
                'revenue_variance' => $revenueVariance,
                'tax_amount' => $this->roundMoney($taxAmount),
            ];
        }

        return $prepared;
    }

    private function persistInvoiceLines(SalesInvoice $invoice, array $preparedLines): array
    {
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($preparedLines as $line) {
            $subtotal += $line['line_total'];
            $taxTotal += $line['tax_amount'];

            $invoice->lines()->create([
                'sales_order_line_id' => $line['sales_order_line_id'],
                'sales_delivery_line_id' => $line['sales_delivery_line_id'],
                'line_number' => $line['line_number'],
                'description' => $line['description'],
                'uom_label' => $line['uom_label'],
                'quantity' => $line['quantity'],
                'quantity_base' => $line['quantity_base'],
                'unit_price' => $line['unit_price'],
                'line_total' => $line['line_total'],
                'line_total_base' => $line['line_total_base'],
                'delivery_value_base' => $line['delivery_value_base'],
                'revenue_variance' => $line['revenue_variance'],
                'tax_amount' => $line['tax_amount'],
            ]);
        }

        return [
            'subtotal' => $this->roundMoney($subtotal),
            'tax_total' => $this->roundMoney($taxTotal),
            'total_amount' => $this->roundMoney($subtotal + $taxTotal),
        ];
    }

    private function preparePostingLines(SalesInvoice $invoice): array
    {
        $prepared = [];

        foreach ($invoice->lines as $line) {
            $deliveryLine = $line->salesDeliveryLine;
            $soLine = $line->salesOrderLine;

            if (!$deliveryLine || !$soLine) {
                throw new SalesInvoiceException('Detail faktur tidak memiliki referensi yang lengkap.');
            }

            $quantity = (float) $line->quantity;
            $quantityBase = $this->deriveBaseQuantity($quantity, $deliveryLine, $soLine);
            $lineTotal = (float) $line->line_total;
            $lineTotalBase = $this->roundCost($lineTotal * (float) $invoice->exchange_rate);
            $taxAmount = (float) $line->tax_amount;
            $taxAmountBase = $this->roundCost($taxAmount * (float) $invoice->exchange_rate);
            $deliveryValueBase = $this->roundCost($quantityBase * (float) $deliveryLine->unit_cost_base);
            $revenueVariance = $this->roundMoney(($lineTotalBase + $taxAmountBase) - $deliveryValueBase);

            $prepared[] = [
                'line_id' => $line->id,
                'sales_order_line_id' => $soLine->id,
                'sales_delivery_line_id' => $deliveryLine->id,
                'quantity' => $this->roundQuantity($quantity),
                'quantity_base' => $quantityBase,
                'line_total' => $lineTotal,
                'line_total_base' => $lineTotalBase,
                'tax_amount' => $taxAmount,
                'tax_amount_base' => $taxAmountBase,
                'delivery_value_base' => $deliveryValueBase,
                'revenue_variance' => $revenueVariance,
            ];
        }

        return $prepared;
    }

    private function assertLineStillAvailable(array $line, SalesOrderLine $soLine, ?SalesDeliveryLine $sdLine): void
    {
        $remainingSo = max(
            0.0,
            ((float) $soLine->quantity_delivered)
                - (float) $soLine->quantity_invoiced
        );

        if (($line['quantity'] - $remainingSo) > self::QTY_TOLERANCE) {
            throw new SalesInvoiceException('Jumlah faktur sudah tidak tersedia pada SO.');
        }

        if ($sdLine) {
            $remainingSd = max(
                0.0,
                (float) $sdLine->quantity
                    - (float) $sdLine->quantity_invoiced
            );

            if (($line['quantity'] - $remainingSd) > self::QTY_TOLERANCE) {
                throw new SalesInvoiceException('Jumlah faktur sudah tidak tersedia pada delivery.');
            }
        }
    }

    private function calculateTotals(array $lines): array
    {
        $subtotal = 0.0;
        $taxTotal = 0.0;
        $deliveryValue = 0.0;
        $revenueVariance = 0.0;

        foreach ($lines as $line) {
            $subtotal += $line['line_total'];
            $taxTotal += $line['tax_amount'];
            $deliveryValue += $line['delivery_value_base'];
            $revenueVariance += $line['revenue_variance'];
        }

        return [
            'subtotal' => $this->roundMoney($subtotal),
            'tax_total' => $this->roundMoney($taxTotal),
            'total_amount' => $this->roundMoney($subtotal + $taxTotal),
            'delivery_value_base' => $this->roundCost($deliveryValue),
            'revenue_variance' => $this->roundMoney($revenueVariance),
        ];
    }

    private function dispatchArPostedEvent(
        SalesInvoice $invoice,
        array $totals,
        ?Authenticatable $actor = null
    ): void {
        if ($totals['subtotal'] <= 0) {
            return;
        }

        $currencyCode = $invoice->currency?->code ?? 'IDR';
        $occurredAt = CarbonImmutable::parse($invoice->invoice_date ?? now());
        $exchangeRate = (float) $invoice->exchange_rate;

        $meta = [
            'sales_order_id' => $invoice->sales_order_id,
            'sales_order_number' => $invoice->salesOrder?->order_number,
        ];

        $payload = new AccountingEventPayload(
            AccountingEventCode::SALES_AR_POSTED,
            $invoice->company_id,
            $invoice->branch_id,
            'sales_invoice',
            $invoice->id,
            $invoice->invoice_number,
            $currencyCode,
            $exchangeRate,
            $occurredAt,
            $actor?->getAuthIdentifier(),
            $meta
        );

        $invoiceBaseAmount = $this->roundCost(($totals['total_amount'] * $exchangeRate));

        $payload->setLines(
            array_filter([
                AccountingEntry::debit('accounts_receivable', $invoiceBaseAmount),
                AccountingEntry::credit('sales_revenue', $totals['delivery_value_base']),
                $totals['revenue_variance'] !== 0.0
                    ? ($totals['revenue_variance'] > 0
                        ? AccountingEntry::credit('revenue_variance', abs($totals['revenue_variance']))
                        : AccountingEntry::debit('revenue_variance', abs($totals['revenue_variance'])))
                    : null,
            ])
        );

        rescue(function () use ($payload) {
            $this->accountingEventBus->dispatch($payload);
        }, static function (Throwable $throwable) {
            report($throwable);
        });
    }

    private function syncSalesOrderBillingStatus(
        SalesOrder $salesOrder,
        ?Authenticatable $actor = null
    ): void {
        $actor ??= Auth::user();

        $hasPending = $salesOrder->lines->contains(function (SalesOrderLine $line) {
            return (
                ((float) $line->quantity_delivered)
                    - (float) $line->quantity_invoiced
            ) > self::QTY_TOLERANCE;
        });

        if ($hasPending) {
            return;
        }

        if (!in_array($salesOrder->status, [
            SalesOrderStatus::DELIVERED->value,
            SalesOrderStatus::PARTIALLY_DELIVERED->value,
        ], true)) {
            return;
        }

        $salesOrder->transitionTo(
            SalesOrderStatus::CLOSED,
            $actor,
            $this->makerCheckerContext($salesOrder->company_id)
        );
    }

    private function assertDraft(SalesInvoice $invoice): void
    {
        if ($invoice->status !== InvoiceStatus::DRAFT->value) {
            throw new SalesInvoiceException('Faktur ini sudah diposting dan tidak dapat diubah.');
        }
    }

    private function assertSalesOrderInvoiceable(SalesOrder $salesOrder): void
    {
        if (!in_array($salesOrder->status, [
            SalesOrderStatus::PARTIALLY_DELIVERED->value,
            SalesOrderStatus::DELIVERED->value,
            SalesOrderStatus::CLOSED->value,
        ], true)) {
            throw new SalesInvoiceException('Sales Order belum memiliki pengiriman.');
        }
    }

    private function generateInvoiceNumber(int $companyId, int $branchId, Carbon $invoiceDate): string
    {
        $config = config('sales.ar_invoice_numbering', []);
        $prefix = strtoupper($config['prefix'] ?? 'SINV');
        $sequencePadding = (int) ($config['sequence_padding'] ?? 5);

        $latest = SalesInvoice::withTrashed()
            ->where('branch_id', $branchId)
            ->whereYear('invoice_date', $invoiceDate->year)
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $nextSequence = 1;

        if ($latest) {
            $segments = explode('.', $latest);
            $last = (int) (end($segments) ?: 0);
            $nextSequence = $last + 1;
        }

        $companySegment = str_pad((string) $companyId, 2, '0', STR_PAD_LEFT);
        $branchSegment = str_pad((string) $branchId, 3, '0', STR_PAD_LEFT);
        $yearSegment = $invoiceDate->format('y');
        $sequence = str_pad((string) $nextSequence, $sequencePadding, '0', STR_PAD_LEFT);

        return sprintf('%s.%s%s.%s.%s', $prefix, $companySegment, $branchSegment, $yearSegment, $sequence);
    }

    private function deriveBaseQuantity(
        float $quantity,
        SalesDeliveryLine $deliveryLine,
        SalesOrderLine $soLine
    ): float {
        $ratio = 1.0;

        if ((float) $deliveryLine->quantity > 0 && (float) $deliveryLine->quantity_base > 0) {
            $ratio = (float) $deliveryLine->quantity_base / (float) $deliveryLine->quantity;
        } elseif ((float) $soLine->quantity > 0 && (float) $soLine->quantity_base > 0) {
            $ratio = (float) $soLine->quantity_base / (float) $soLine->quantity;
        }

        return $this->roundQuantity($quantity * $ratio);
    }

    private function makerCheckerContext(?int $companyId): array
    {
        unset($companyId);

        return [
            'enforceMakerChecker' => (bool) config('sales.maker_checker.enforce', false),
        ];
    }

    private function roundMoney(float $value): float
    {
        return round($value, 2);
    }

    private function roundQuantity(float $value): float
    {
        return round($value, 3);
    }

    private function roundCost(float $value): float
    {
        return round($value, self::COST_SCALE);
    }
}
