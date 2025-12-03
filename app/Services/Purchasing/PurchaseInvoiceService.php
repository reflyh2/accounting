<?php

namespace App\Services\Purchasing;

use App\Domain\Accounting\DTO\AccountingEntry;
use App\Domain\Accounting\DTO\AccountingEventPayload;
use App\Enums\AccountingEventCode;
use App\Enums\Documents\InvoiceStatus;
use App\Enums\Documents\PurchaseOrderStatus;
use App\Exceptions\PurchaseInvoiceException;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\Accounting\AccountingEventBus;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class PurchaseInvoiceService
{
    private const QTY_TOLERANCE = 0.0005;
    private const COST_SCALE = 6;

    public function __construct(
        private readonly AccountingEventBus $accountingEventBus,
    ) {
    }

    public function create(array $payload, ?Authenticatable $actor = null): PurchaseInvoice
    {
        $actor ??= Auth::user();

        return DB::transaction(function () use ($payload, $actor) {
            /** @var PurchaseOrder $purchaseOrder */
            $purchaseOrder = PurchaseOrder::with(['branch.branchGroup', 'currency', 'partner'])
                ->findOrFail($payload['purchase_order_id']);

            $this->assertPurchaseOrderInvoiceable($purchaseOrder);

            $invoiceDate = Carbon::parse($payload['invoice_date']);
            $exchangeRate = (float) ($payload['exchange_rate'] ?? $purchaseOrder->exchange_rate ?? 1);
            $preparedLines = $this->prepareLines(
                $purchaseOrder,
                $payload['lines'] ?? [],
                $exchangeRate
            );

            if (empty($preparedLines)) {
                throw new PurchaseInvoiceException('Minimal satu baris faktur wajib diisi.');
            }

            $invoice = PurchaseInvoice::create([
                'purchase_order_id' => $purchaseOrder->id,
                'company_id' => $purchaseOrder->company_id,
                'branch_id' => $purchaseOrder->branch_id,
                'partner_id' => $purchaseOrder->partner_id,
                'currency_id' => $purchaseOrder->currency_id,
                'invoice_number' => $this->generateInvoiceNumber(
                    $purchaseOrder->company_id,
                    $purchaseOrder->branch_id,
                    $invoiceDate
                ),
                'invoice_date' => $invoiceDate,
                'due_date' => isset($payload['due_date']) ? Carbon::parse($payload['due_date']) : null,
                'vendor_invoice_number' => $payload['vendor_invoice_number'] ?? null,
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
                'purchaseOrder.partner',
                'purchaseOrder.branch',
                'currency',
                'lines.goodsReceiptLine.goodsReceipt',
                'lines.purchaseOrderLine',
            ]);
        });
    }

    public function update(PurchaseInvoice $invoice, array $payload, ?Authenticatable $actor = null): PurchaseInvoice
    {
        $this->assertDraft($invoice);

        if ((int) $invoice->purchase_order_id !== (int) $payload['purchase_order_id']) {
            throw new PurchaseInvoiceException('Purchase Order tidak dapat diubah setelah faktur dibuat.');
        }

        $actor ??= Auth::user();

        return DB::transaction(function () use ($invoice, $payload, $actor) {
            $invoice->load('purchaseOrder.branch.branchGroup', 'purchaseOrder.currency');

            $purchaseOrder = $invoice->purchaseOrder;

            $invoiceDate = Carbon::parse($payload['invoice_date']);
            $exchangeRate = (float) ($payload['exchange_rate'] ?? $invoice->exchange_rate ?? 1);

            $preparedLines = $this->prepareLines(
                $purchaseOrder,
                $payload['lines'] ?? [],
                $exchangeRate
            );

            if (empty($preparedLines)) {
                throw new PurchaseInvoiceException('Minimal satu baris faktur wajib diisi.');
            }

            $invoice->lines()->delete();
            $totals = $this->persistInvoiceLines($invoice, $preparedLines);

            $invoice->update([
                'invoice_date' => $invoiceDate,
                'due_date' => isset($payload['due_date']) ? Carbon::parse($payload['due_date']) : null,
                'vendor_invoice_number' => $payload['vendor_invoice_number'] ?? null,
                'exchange_rate' => $exchangeRate,
                'notes' => $payload['notes'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total_amount' => $totals['total_amount'],
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            return $invoice->fresh([
                'purchaseOrder.partner',
                'lines.goodsReceiptLine.goodsReceipt',
                'lines.purchaseOrderLine',
            ]);
        });
    }

    public function delete(PurchaseInvoice $invoice): void
    {
        $this->assertDraft($invoice);

        DB::transaction(function () use ($invoice) {
            $invoice->lines()->delete();
            $invoice->delete();
        });
    }

    public function post(PurchaseInvoice $invoice, ?Authenticatable $actor = null): PurchaseInvoice
    {
        $this->assertDraft($invoice);
        $actor ??= Auth::user();

        return DB::transaction(function () use ($invoice, $actor) {
            $invoice->load([
                'purchaseOrder.branch.branchGroup.company',
                'purchaseOrder.lines',
                'lines.purchaseOrderLine',
                'lines.goodsReceiptLine.goodsReceipt',
                'currency',
            ]);

            $prepared = $this->preparePostingLines($invoice);

            $poLineIds = collect($prepared)->pluck('purchase_order_line_id')->unique()->values();
            $grnLineIds = collect($prepared)->pluck('goods_receipt_line_id')->filter()->unique()->values();

            /** @var Collection<int, PurchaseOrderLine> $lockedPoLines */
            $lockedPoLines = PurchaseOrderLine::whereIn('id', $poLineIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Collection<int, GoodsReceiptLine> $lockedGrnLines */
            $lockedGrnLines = GoodsReceiptLine::whereIn('id', $grnLineIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($prepared as $line) {
                /** @var PurchaseOrderLine $poLine */
                $poLine = $lockedPoLines->get($line['purchase_order_line_id']);
                if (!$poLine) {
                    throw new PurchaseInvoiceException('Baris PO tidak ditemukan saat posting faktur.');
                }

                $this->assertLineStillAvailable(
                    $line,
                    $poLine,
                    $lockedGrnLines->get($line['goods_receipt_line_id'])
                );

                $poLine->quantity_invoiced = $this->roundQuantity(
                    (float) $poLine->quantity_invoiced + $line['quantity']
                );
                $poLine->quantity_invoiced_base = $this->roundQuantity(
                    (float) $poLine->quantity_invoiced_base + $line['quantity_base']
                );
                $poLine->amount_invoiced = $this->roundMoney(
                    (float) $poLine->amount_invoiced + $line['line_total']
                );
                $poLine->save();

                if ($line['goods_receipt_line_id']) {
                    /** @var GoodsReceiptLine $grnLine */
                    $grnLine = $lockedGrnLines->get($line['goods_receipt_line_id']);
                    if ($grnLine) {
                        $grnLine->quantity_invoiced = $this->roundQuantity(
                            (float) $grnLine->quantity_invoiced + $line['quantity']
                        );
                        $grnLine->quantity_invoiced_base = $this->roundQuantity(
                            (float) $grnLine->quantity_invoiced_base + $line['quantity_base']
                        );
                        $grnLine->amount_invoiced = $this->roundMoney(
                            (float) $grnLine->amount_invoiced + $line['line_total']
                        );
                        $grnLine->save();
                    }
                }

                PurchaseInvoiceLine::whereKey($line['line_id'])
                    ->update([
                        'quantity_base' => $line['quantity_base'],
                        'line_total_base' => $line['line_total_base'],
                        'grn_value_base' => $line['grn_value_base'],
                        'ppv_amount' => $line['ppv_amount'],
                        'tax_amount' => $line['tax_amount'],
                    ]);
            }

            $totals = $this->calculateTotals($prepared);

            $invoice->update([
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total_amount' => $totals['total_amount'],
                'grn_value_base' => $totals['grn_value_base'],
                'ppv_amount' => $totals['ppv_total'],
                'status' => InvoiceStatus::POSTED->value,
                'posted_at' => now(),
                'posted_by' => $actor?->getAuthIdentifier(),
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            $invoice->purchaseOrder->refresh();
            $invoice->purchaseOrder->loadMissing('lines');

            $this->syncPurchaseOrderBillingStatus($invoice->purchaseOrder, $actor);
            $this->dispatchApPostedEvent($invoice->fresh('currency'), $totals, $actor);

            return $invoice->fresh([
                'purchaseOrder.partner',
                'lines.goodsReceiptLine.goodsReceipt',
                'lines.purchaseOrderLine',
            ]);
        });
    }

    private function prepareLines(
        PurchaseOrder $purchaseOrder,
        array $lines,
        float $exchangeRate
    ): array {
        if (empty($lines)) {
            return [];
        }

        $poLines = $purchaseOrder->lines()
            ->with(['uom', 'baseUom'])
            ->get()
            ->keyBy('id');

        $receiptLines = GoodsReceiptLine::query()
            ->with('goodsReceipt')
            ->whereIn('purchase_order_line_id', $poLines->keys())
            ->get()
            ->keyBy('id');

        $prepared = [];
        $lineNumber = 1;

        foreach ($lines as $payloadLine) {
            $purchaseOrderLineId = (int) ($payloadLine['purchase_order_line_id'] ?? 0);
            $goodsReceiptLineId = (int) ($payloadLine['goods_receipt_line_id'] ?? 0);
            $quantity = (float) ($payloadLine['quantity'] ?? 0);
            $unitPrice = (float) ($payloadLine['unit_price'] ?? 0);
            $taxAmount = (float) ($payloadLine['tax_amount'] ?? 0);

            if ($quantity <= 0) {
                throw new PurchaseInvoiceException('Jumlah faktur harus lebih dari nol.');
            }

            if ($unitPrice < 0) {
                throw new PurchaseInvoiceException('Harga satuan tidak boleh bernilai negatif.');
            }

            /** @var PurchaseOrderLine|null $poLine */
            $poLine = $poLines->get($purchaseOrderLineId);
            if (!$poLine) {
                throw new PurchaseInvoiceException('Baris Purchase Order tidak ditemukan.');
            }

            if (!$goodsReceiptLineId) {
                throw new PurchaseInvoiceException('Baris penerimaan harus dipilih.');
            }

            /** @var GoodsReceiptLine|null $receiptLine */
            $receiptLine = $receiptLines->get($goodsReceiptLineId);
            if (!$receiptLine || (int) $receiptLine->purchase_order_line_id !== $poLine->id) {
                throw new PurchaseInvoiceException('Baris penerimaan tidak sah untuk PO tersebut.');
            }

            $availableQuantity = max(
                0.0,
                (float) $receiptLine->quantity
                    - (float) $receiptLine->quantity_invoiced
                    - (float) $receiptLine->quantity_returned
            );

            if (($quantity - $availableQuantity) > self::QTY_TOLERANCE) {
                throw new PurchaseInvoiceException('Jumlah faktur melebihi sisa pada GRN.');
            }

            $poAvailable = max(
                0.0,
                ((float) $poLine->quantity_received - (float) $poLine->quantity_returned)
                    - (float) $poLine->quantity_invoiced
            );

            if (($quantity - $poAvailable) > self::QTY_TOLERANCE) {
                throw new PurchaseInvoiceException('Jumlah faktur melebihi sisa penerimaan PO.');
            }

            $quantityBase = $this->deriveBaseQuantity($quantity, $receiptLine, $poLine);
            $lineTotal = $this->roundMoney($quantity * $unitPrice);
            $lineTotalBase = $this->roundCost($lineTotal * $exchangeRate);
            $grnValueBase = $this->roundCost($quantityBase * (float) $receiptLine->unit_cost_base);
            $ppvAmount = $this->roundMoney(($lineTotalBase + ($taxAmount * $exchangeRate)) - $grnValueBase);

            $prepared[] = [
                'line_number' => $lineNumber++,
                'purchase_order_line_id' => $poLine->id,
                'goods_receipt_line_id' => $receiptLine->id,
                'description' => $payloadLine['description'] ?? $poLine->description,
                'uom_label' => $poLine->uom?->name,
                'quantity' => $this->roundQuantity($quantity),
                'quantity_base' => $quantityBase,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'line_total_base' => $lineTotalBase,
                'grn_value_base' => $grnValueBase,
                'ppv_amount' => $ppvAmount,
                'tax_amount' => $this->roundMoney($taxAmount),
            ];
        }

        return $prepared;
    }

    private function persistInvoiceLines(PurchaseInvoice $invoice, array $preparedLines): array
    {
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($preparedLines as $line) {
            $subtotal += $line['line_total'];
            $taxTotal += $line['tax_amount'];

            $invoice->lines()->create([
                'purchase_order_line_id' => $line['purchase_order_line_id'],
                'goods_receipt_line_id' => $line['goods_receipt_line_id'],
                'line_number' => $line['line_number'],
                'description' => $line['description'],
                'uom_label' => $line['uom_label'],
                'quantity' => $line['quantity'],
                'quantity_base' => $line['quantity_base'],
                'unit_price' => $line['unit_price'],
                'line_total' => $line['line_total'],
                'line_total_base' => $line['line_total_base'],
                'grn_value_base' => $line['grn_value_base'],
                'ppv_amount' => $line['ppv_amount'],
                'tax_amount' => $line['tax_amount'],
            ]);
        }

        return [
            'subtotal' => $this->roundMoney($subtotal),
            'tax_total' => $this->roundMoney($taxTotal),
            'total_amount' => $this->roundMoney($subtotal + $taxTotal),
        ];
    }

    private function preparePostingLines(PurchaseInvoice $invoice): array
    {
        $prepared = [];

        foreach ($invoice->lines as $line) {
            $receiptLine = $line->goodsReceiptLine;
            $poLine = $line->purchaseOrderLine;

            if (!$receiptLine || !$poLine) {
                throw new PurchaseInvoiceException('Detail faktur tidak memiliki referensi yang lengkap.');
            }

            $quantity = (float) $line->quantity;
            $quantityBase = $this->deriveBaseQuantity($quantity, $receiptLine, $poLine);
            $lineTotal = (float) $line->line_total;
            $lineTotalBase = $this->roundCost($lineTotal * (float) $invoice->exchange_rate);
            $taxAmount = (float) $line->tax_amount;
            $taxAmountBase = $this->roundCost($taxAmount * (float) $invoice->exchange_rate);
            $grnValueBase = $this->roundCost($quantityBase * (float) $receiptLine->unit_cost_base);
            $ppvAmount = $this->roundMoney(($lineTotalBase + $taxAmountBase) - $grnValueBase);

            $prepared[] = [
                'line_id' => $line->id,
                'purchase_order_line_id' => $poLine->id,
                'goods_receipt_line_id' => $receiptLine->id,
                'quantity' => $this->roundQuantity($quantity),
                'quantity_base' => $quantityBase,
                'line_total' => $lineTotal,
                'line_total_base' => $lineTotalBase,
                'tax_amount' => $taxAmount,
                'tax_amount_base' => $taxAmountBase,
                'grn_value_base' => $grnValueBase,
                'ppv_amount' => $ppvAmount,
            ];
        }

        return $prepared;
    }

    private function assertLineStillAvailable(array $line, PurchaseOrderLine $poLine, ?GoodsReceiptLine $grnLine): void
    {
        $remainingPo = max(
            0.0,
            ((float) $poLine->quantity_received - (float) $poLine->quantity_returned)
                - (float) $poLine->quantity_invoiced
        );

        if (($line['quantity'] - $remainingPo) > self::QTY_TOLERANCE) {
            throw new PurchaseInvoiceException('Jumlah faktur sudah tidak tersedia pada PO.');
        }

        if ($grnLine) {
            $remainingGrn = max(
                0.0,
                (float) $grnLine->quantity
                    - (float) $grnLine->quantity_invoiced
                    - (float) $grnLine->quantity_returned
            );

            if (($line['quantity'] - $remainingGrn) > self::QTY_TOLERANCE) {
                throw new PurchaseInvoiceException('Jumlah faktur sudah tidak tersedia pada GRN.');
            }
        }
    }

    private function calculateTotals(array $lines): array
    {
        $subtotal = 0.0;
        $taxTotal = 0.0;
        $grnValue = 0.0;
        $ppvTotal = 0.0;

        foreach ($lines as $line) {
            $subtotal += $line['line_total'];
            $taxTotal += $line['tax_amount'];
            $grnValue += $line['grn_value_base'];
            $ppvTotal += $line['ppv_amount'];
        }

        return [
            'subtotal' => $this->roundMoney($subtotal),
            'tax_total' => $this->roundMoney($taxTotal),
            'total_amount' => $this->roundMoney($subtotal + $taxTotal),
            'grn_value_base' => $this->roundCost($grnValue),
            'ppv_total' => $this->roundMoney($ppvTotal),
        ];
    }

    private function dispatchApPostedEvent(
        PurchaseInvoice $invoice,
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
            'purchase_order_id' => $invoice->purchase_order_id,
            'purchase_order_number' => $invoice->purchaseOrder?->order_number,
        ];

        $payload = new AccountingEventPayload(
            AccountingEventCode::PURCHASE_AP_POSTED,
            $invoice->company_id,
            $invoice->branch_id,
            'purchase_invoice',
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
                AccountingEntry::debit('goods_received_not_invoiced', $totals['grn_value_base']),
                $totals['ppv_total'] !== 0.0
                    ? ($totals['ppv_total'] > 0
                        ? AccountingEntry::debit('purchase_price_variance', abs($totals['ppv_total']))
                        : AccountingEntry::credit('purchase_price_variance', abs($totals['ppv_total'])))
                    : null,
                AccountingEntry::credit('accounts_payable', $invoiceBaseAmount),
            ])
        );

        rescue(function () use ($payload) {
            $this->accountingEventBus->dispatch($payload);
        }, static function (Throwable $throwable) {
            report($throwable);
        });
    }

    private function syncPurchaseOrderBillingStatus(
        PurchaseOrder $purchaseOrder,
        ?Authenticatable $actor = null
    ): void {
        $actor ??= Auth::user();

        $hasPending = $purchaseOrder->lines->contains(function (PurchaseOrderLine $line) {
            return (
                ((float) $line->quantity_received - (float) $line->quantity_returned)
                    - (float) $line->quantity_invoiced
            ) > self::QTY_TOLERANCE;
        });

        if ($hasPending) {
            return;
        }

        if (!in_array($purchaseOrder->status, [
            PurchaseOrderStatus::RECEIVED->value,
            PurchaseOrderStatus::PARTIALLY_RECEIVED->value,
        ], true)) {
            return;
        }

        $purchaseOrder->transitionTo(
            PurchaseOrderStatus::CLOSED,
            $actor,
            $this->makerCheckerContext($purchaseOrder->company_id)
        );
    }

    private function assertDraft(PurchaseInvoice $invoice): void
    {
        if ($invoice->status !== InvoiceStatus::DRAFT->value) {
            throw new PurchaseInvoiceException('Faktur ini sudah diposting dan tidak dapat diubah.');
        }
    }

    private function assertPurchaseOrderInvoiceable(PurchaseOrder $purchaseOrder): void
    {
        if (!in_array($purchaseOrder->status, [
            PurchaseOrderStatus::PARTIALLY_RECEIVED->value,
            PurchaseOrderStatus::RECEIVED->value,
            PurchaseOrderStatus::CLOSED->value,
        ], true)) {
            throw new PurchaseInvoiceException('Purchase Order belum memiliki penerimaan.');
        }
    }

    private function generateInvoiceNumber(int $companyId, int $branchId, Carbon $invoiceDate): string
    {
        $config = config('purchasing.ap_invoice_numbering', []);
        $prefix = strtoupper($config['prefix'] ?? 'PINV');
        $sequencePadding = (int) ($config['sequence_padding'] ?? 5);

        $latest = PurchaseInvoice::withTrashed()
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
        GoodsReceiptLine $receiptLine,
        PurchaseOrderLine $poLine
    ): float {
        $ratio = 1.0;

        if ((float) $receiptLine->quantity > 0 && (float) $receiptLine->quantity_base > 0) {
            $ratio = (float) $receiptLine->quantity_base / (float) $receiptLine->quantity;
        } elseif ((float) $poLine->quantity > 0 && (float) $poLine->quantity_base > 0) {
            $ratio = (float) $poLine->quantity_base / (float) $poLine->quantity;
        }

        return $this->roundQuantity($quantity * $ratio);
    }

    private function makerCheckerContext(?int $companyId): array
    {
        unset($companyId);

        return [
            'enforceMakerChecker' => (bool) config('purchasing.maker_checker.enforce', false),
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

