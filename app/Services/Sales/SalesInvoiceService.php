<?php

namespace App\Services\Sales;

use App\Domain\Accounting\DTO\AccountingEntry;
use App\Domain\Accounting\DTO\AccountingEventPayload;
use App\Enums\AccountingEventCode;
use App\Enums\Documents\InvoiceStatus;
use App\Enums\Documents\SalesOrderStatus;
use App\Events\Debt\ExternalDebtCreated;
use App\Exceptions\SalesInvoiceException;
use App\Models\Currency;
use App\Models\ExternalDebt;
use App\Models\Product;
use App\Models\SalesDeliveryLine;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceCost;
use App\Models\SalesInvoiceLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Services\Accounting\AccountingEventBus;
use App\Services\Catalog\UserDiscountLimitResolver;
use App\Services\Costing\CostingService;
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
        private readonly UserDiscountLimitResolver $discountLimitResolver,
        private readonly CostingService $costingService,
    ) {}

    /**
     * Create a sales invoice from multiple Sales Orders or as a direct invoice.
     */
    public function create(array $payload, ?Authenticatable $actor = null): SalesInvoice
    {
        $actor ??= Auth::user();

        return DB::transaction(function () use ($payload, $actor) {
            $soIds = $payload['sales_order_ids'] ?? [];
            if (! is_array($soIds)) {
                $soIds = $soIds ? [$soIds] : [];
            }
            $soIds = array_unique(array_filter(array_map('intval', $soIds)));

            $salesOrders = collect();
            if (! empty($soIds)) {
                $salesOrders = SalesOrder::with(['branch.branchGroup', 'currency', 'partner', 'paymentTerm'])
                    ->whereIn('id', $soIds)
                    ->get();

                if ($salesOrders->count() !== count($soIds)) {
                    throw new SalesInvoiceException('Beberapa Sales Order tidak ditemukan.');
                }

                $this->validateSalesOrdersConsistency($salesOrders);
                foreach ($salesOrders as $so) {
                    $this->assertSalesOrderInvoiceable($so);
                }
            }

            // Determine header data from first SO or payload
            $firstSo = $salesOrders->first();
            $companyId = $firstSo?->company_id ?? $payload['company_id'] ?? null;
            $branchId = $firstSo?->branch_id ?? $payload['branch_id'] ?? null;
            $partnerId = $firstSo?->partner_id ?? $payload['partner_id'] ?? null;
            $currencyId = $firstSo?->currency_id ?? $payload['currency_id'] ?? null;

            if (! $companyId || ! $branchId || ! $partnerId || ! $currencyId) {
                throw new SalesInvoiceException('Data perusahaan, cabang, pelanggan, dan mata uang wajib diisi.');
            }

            $invoiceDate = Carbon::parse($payload['invoice_date']);
            $exchangeRate = (float) ($payload['exchange_rate'] ?? $firstSo?->exchange_rate ?? 1);

            // Calculate due_date if not provided
            $dueDate = null;
            if (! empty($payload['due_date'])) {
                $dueDate = Carbon::parse($payload['due_date']);
            } elseif ($firstSo && $firstSo->paymentTerm) {
                // Auto-calculate from payment term
                $dueDate = $invoiceDate->copy()->addDays($firstSo->paymentTerm->days);
            }

            if ($salesOrders->isNotEmpty()) {
                $preparedLines = $this->prepareLines(
                    $salesOrders,
                    $payload['lines'] ?? [],
                    $exchangeRate
                );
            } else {
                $preparedLines = $this->prepareDirectLines(
                    $payload['lines'] ?? [],
                    $exchangeRate
                );
            }

            if (empty($preparedLines)) {
                throw new SalesInvoiceException('Minimal satu baris faktur wajib diisi.');
            }

            // Create Invoice
            $invoice = SalesInvoice::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'partner_id' => $partnerId,
                'currency_id' => $currencyId,
                'invoice_number' => $this->generateInvoiceNumber(
                    $companyId,
                    $branchId,
                    $invoiceDate
                ),
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'customer_invoice_number' => $payload['customer_invoice_number'] ?? null,
                'tax_invoice_code' => $payload['tax_invoice_code'] ?? '01',
                'exchange_rate' => $exchangeRate,
                'notes' => $payload['notes'] ?? null,
                'payment_method' => $payload['payment_method'] ?? null,
                'company_bank_account_id' => $payload['company_bank_account_id'] ?? null,
                'sales_person_id' => $payload['sales_person_id'] ?? $actor?->getAuthIdentifier(),
                'invoice_address_id' => $payload['invoice_address_id'] ?? null,
                'shipping_charge' => $payload['shipping_charge'] ?? 0,
                'created_by' => $actor?->getAuthIdentifier(),
            ]);

            if ($salesOrders->isNotEmpty()) {
                $invoice->salesOrders()->attach($salesOrders->pluck('id'));
            }

            $shippingCharge = (float) ($payload['shipping_charge'] ?? 0);
            $totals = $this->persistInvoiceLines($invoice, $preparedLines, $shippingCharge);

            $invoice->update([
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total_amount' => $totals['total_amount'],
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            // Persist costs if provided
            $this->persistCosts($invoice, $payload['costs'] ?? []);

            return $invoice->fresh([
                'salesOrders.partner',
                'salesOrders.branch',
                'currency',
                'lines.salesDeliveryLine.salesDelivery',
                'lines.salesOrderLine',
                'costs.costItem',
            ]);
        });
    }

    /**
     * Update an existing sales invoice.
     */
    public function update(SalesInvoice $invoice, array $payload, ?Authenticatable $actor = null): SalesInvoice
    {
        $this->assertDraft($invoice);
        $actor ??= Auth::user();

        return DB::transaction(function () use ($invoice, $payload, $actor) {
            $invoice->load('salesOrders.branch.branchGroup', 'salesOrders.currency');

            $salesOrders = $invoice->salesOrders;
            $isDirectInvoice = $salesOrders->isEmpty();

            $invoiceDate = Carbon::parse($payload['invoice_date']);
            $exchangeRate = (float) ($payload['exchange_rate'] ?? $invoice->exchange_rate ?? 1);

            if ($isDirectInvoice) {
                $preparedLines = $this->prepareDirectLines(
                    $payload['lines'] ?? [],
                    $exchangeRate
                );
            } else {
                $preparedLines = $this->prepareLines(
                    $salesOrders,
                    $payload['lines'] ?? [],
                    $exchangeRate
                );
            }

            if (empty($preparedLines)) {
                throw new SalesInvoiceException('Minimal satu baris faktur wajib diisi.');
            }

            $invoice->lines()->delete();
            $shippingCharge = (float) ($payload['shipping_charge'] ?? 0);
            $totals = $this->persistInvoiceLines($invoice, $preparedLines, $shippingCharge);

            $invoice->update([
                'invoice_date' => $invoiceDate,
                'due_date' => ! empty($payload['due_date']) ? Carbon::parse($payload['due_date']) : null,
                'customer_invoice_number' => $payload['customer_invoice_number'] ?? null,
                'tax_invoice_code' => $payload['tax_invoice_code'] ?? $invoice->tax_invoice_code ?? '01',
                'exchange_rate' => $exchangeRate,
                'notes' => $payload['notes'] ?? null,
                'payment_method' => $payload['payment_method'] ?? null,
                'company_bank_account_id' => $payload['company_bank_account_id'] ?? null,
                'sales_person_id' => $payload['sales_person_id'] ?? $invoice->sales_person_id,
                'invoice_address_id' => $payload['invoice_address_id'] ?? null,
                'shipping_charge' => $shippingCharge,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total_amount' => $totals['total_amount'],
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            // Persist costs if provided
            $this->persistCosts($invoice, $payload['costs'] ?? []);

            return $invoice->fresh([
                'salesOrders.partner',
                'lines.salesOrderLine',
                'lines.salesDeliveryLine.salesDelivery',
                'costs.costItem',
            ]);
        });
    }

    /**
     * Delete a draft sales invoice.
     */
    public function delete(SalesInvoice $invoice): void
    {
        $this->assertDraft($invoice);

        DB::transaction(function () use ($invoice) {
            $invoice->salesOrders()->detach();
            $invoice->lines()->delete();
            $invoice->delete();
        });
    }

    /**
     * Post a sales invoice, updating SO/SD line invoiced quantities.
     */
    public function post(SalesInvoice $invoice, ?Authenticatable $actor = null): SalesInvoice
    {
        $this->assertDraft($invoice);
        $actor ??= Auth::user();

        $invoice->loadMissing([
            'salesOrders.lines',
            'salesOrders.branch.branchGroup.company',
            'lines.salesOrderLine.product',
            'lines.salesDeliveryLine.salesDelivery',
            'currency',
        ]);

        // Validate discount authorization
        $this->validateDiscountAuthorization($invoice, $actor);

        $isDirectInvoice = $invoice->salesOrders->isEmpty();

        if ($isDirectInvoice) {
            return $this->postDirectInvoice($invoice, $actor);
        }

        return $this->postSoInvoice($invoice, $actor);
    }

    /**
     * Post an invoice linked to Sales Orders.
     */
    private function postSoInvoice(SalesInvoice $invoice, ?Authenticatable $actor): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $actor) {
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
                if (! $soLine) {
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

            $totals = $this->calculateTotals($prepared, (float) $invoice->shipping_charge);

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

            // Sync all SO statuses
            foreach ($invoice->salesOrders as $so) {
                $so->refresh();
                $so->loadMissing('lines');
                $this->syncSalesOrderBillingStatus($so, $actor);
            }

            $this->dispatchArPostedEvent($invoice->fresh('currency'), $totals, $actor);

            // Create External Debt record for AR tracking
            $this->createExternalDebt($invoice, $actor);

            // Create CostEntry records from invoice costs
            $this->createCostEntries($invoice, $actor);

            return $invoice->fresh([
                'salesOrders.partner',
                'lines.salesOrderLine',
                'lines.salesDeliveryLine.salesDelivery',
                'externalDebt',
            ]);
        });
    }

    /**
     * Post a direct invoice (no Sales Order).
     */
    private function postDirectInvoice(SalesInvoice $invoice, ?Authenticatable $actor): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $actor) {
            $subtotal = 0.0;
            $taxTotal = 0.0;

            foreach ($invoice->lines as $line) {
                $subtotal += (float) $line->line_total;
                $taxTotal += (float) $line->tax_amount;
            }

            $shippingCharge = (float) $invoice->shipping_charge;

            $totals = [
                'subtotal' => $this->roundMoney($subtotal),
                'tax_total' => $this->roundMoney($taxTotal),
                'total_amount' => $this->roundMoney($subtotal + $taxTotal + $shippingCharge),
                'delivery_value_base' => 0.0,
                'revenue_variance' => 0.0,
            ];

            $invoice->update([
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total_amount' => $totals['total_amount'],
                'status' => InvoiceStatus::POSTED->value,
                'posted_at' => now(),
                'posted_by' => $actor?->getAuthIdentifier(),
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            $this->dispatchDirectArPostedEvent($invoice, $actor);

            // Create External Debt record for AR tracking
            $this->createExternalDebt($invoice, $actor);

            // Create CostEntry records from invoice costs
            $this->createCostEntries($invoice, $actor);

            return $invoice->fresh(['lines', 'currency', 'externalDebt', 'costs']);
        });
    }

    /**
     * Validate that all Sales Orders belong to same customer/company/branch/currency.
     */
    private function validateSalesOrdersConsistency(Collection $salesOrders): void
    {
        if ($salesOrders->count() <= 1) {
            return;
        }

        $partnerIds = $salesOrders->pluck('partner_id')->unique();
        $currencyIds = $salesOrders->pluck('currency_id')->unique();
        $companyIds = $salesOrders->pluck('company_id')->unique();
        $branchIds = $salesOrders->pluck('branch_id')->unique();

        if ($partnerIds->count() > 1) {
            throw new SalesInvoiceException('Semua Sales Order harus memiliki customer yang sama.');
        }

        if ($currencyIds->count() > 1) {
            throw new SalesInvoiceException('Semua Sales Order harus memiliki mata uang yang sama.');
        }

        if ($companyIds->count() > 1) {
            throw new SalesInvoiceException('Semua Sales Order harus berasal dari perusahaan yang sama.');
        }

        if ($branchIds->count() > 1) {
            throw new SalesInvoiceException('Semua Sales Order harus berasal dari cabang yang sama.');
        }
    }

    /**
     * Validate that the posting user has authorization for all discount rates in the invoice.
     *
     * @throws SalesInvoiceException if the user is not authorized
     */
    private function validateDiscountAuthorization(SalesInvoice $invoice, ?Authenticatable $actor): void
    {
        if (! $actor) {
            throw new SalesInvoiceException('User harus login untuk posting faktur.');
        }

        $userGlobalId = $actor->global_id ?? $actor->getAuthIdentifier();

        foreach ($invoice->lines as $line) {
            $discountRate = (float) $line->discount_rate;

            // If no discount, skip validation
            if ($discountRate <= 0) {
                continue;
            }

            // Get product ID from invoice line or from SO line
            $productId = $line->product_id;
            if (! $productId && $line->salesOrderLine) {
                $productId = $line->salesOrderLine->product_id;
            }

            // Get category ID from product if available
            $categoryId = null;
            if ($productId && $line->salesOrderLine?->product) {
                $categoryId = $line->salesOrderLine->product->product_category_id;
            }

            // Check if user is authorized for this discount
            if (! $this->discountLimitResolver->validateDiscount($userGlobalId, $productId, $categoryId, $discountRate)) {
                $limit = $this->discountLimitResolver->resolve($userGlobalId, $productId, $categoryId);
                $limitText = $limit !== null ? "{$limit}%" : '0%';
                $productName = $line->description ?? ($line->salesOrderLine?->product?->name ?? 'item');

                throw new SalesInvoiceException(
                    "Diskon {$discountRate}% pada \"{$productName}\" melebihi batas otoritas Anda ({$limitText}). ".
                    'Minta user dengan otoritas lebih tinggi untuk posting faktur ini.'
                );
            }
        }
    }

    /**
     * Prepare invoice lines from multiple Sales Orders.
     */
    private function prepareLines(
        Collection $salesOrders,
        array $lines,
        float $exchangeRate
    ): array {
        if (empty($lines)) {
            return [];
        }

        // Build combined lookup of all SO lines
        $soLines = collect();
        foreach ($salesOrders as $so) {
            foreach ($so->lines()->with(['uom', 'baseUom'])->get() as $line) {
                $soLines[$line->id] = $line;
            }
        }

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
            $discountRate = (float) ($payloadLine['discount_rate'] ?? 0);
            $taxRate = (float) ($payloadLine['tax_rate'] ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            if ($unitPrice < 0) {
                throw new SalesInvoiceException('Harga satuan tidak boleh bernilai negatif.');
            }

            /** @var SalesOrderLine|null $soLine */
            $soLine = $soLines->get($salesOrderLineId);
            if (! $soLine) {
                throw new SalesInvoiceException('Baris Sales Order tidak ditemukan.');
            }

            /** @var SalesDeliveryLine|null $deliveryLine */
            $deliveryLine = $salesDeliveryLineId ? $deliveryLines->get($salesDeliveryLineId) : null;

            if (! $deliveryLine) {
                if ($salesDeliveryLineId) {
                    throw new SalesInvoiceException('Baris pengiriman tidak ditemukan referensinya.');
                }
                if (! $soLine->resource_pool_id) {
                    throw new SalesInvoiceException('Baris pengiriman harus dipilih untuk item barang.');
                }
            } elseif ((int) $deliveryLine->sales_order_line_id !== $soLine->id) {
                throw new SalesInvoiceException('Baris pengiriman tidak sah untuk SO tersebut.');
            }

            if ($deliveryLine) {
                $availableQuantity = max(
                    0.0,
                    (float) $deliveryLine->quantity
                        - (float) $deliveryLine->quantity_invoiced
                );

                if (($quantity - $availableQuantity) > self::QTY_TOLERANCE) {
                    throw new SalesInvoiceException('Jumlah faktur melebihi sisa pada delivery.');
                }
            }

            if ($soLine->resource_pool_id) {
                $soAvailable = max(
                    0.0,
                    (float) $soLine->quantity - (float) $soLine->quantity_invoiced
                );

                if (($quantity - $soAvailable) > self::QTY_TOLERANCE) {
                    throw new SalesInvoiceException('Jumlah faktur melebihi sisa pesanan (booking).');
                }
            } else {
                $soAvailable = max(
                    0.0,
                    ((float) $soLine->quantity_delivered)
                        - (float) $soLine->quantity_invoiced
                );

                if (($quantity - $soAvailable) > self::QTY_TOLERANCE) {
                    throw new SalesInvoiceException('Jumlah faktur melebihi sisa pengiriman SO.');
                }
            }

            $quantityBase = $this->deriveBaseQuantity($quantity, $deliveryLine, $soLine);
            $grossTotal = $this->roundMoney($quantity * $unitPrice);
            $discountAmount = $this->roundMoney($grossTotal * ($discountRate / 100));
            $lineTotal = $this->roundMoney($grossTotal - $discountAmount);
            $taxAmount = $this->roundMoney($lineTotal * ($taxRate / 100));
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
                'discount_rate' => $discountRate,
                'discount_amount' => $discountAmount,
                'tax_rate' => $taxRate,
                'tax_amount' => $this->roundMoney($taxAmount),
                'line_total' => $lineTotal,
                'line_total_base' => $lineTotalBase,
                'delivery_value_base' => $deliveryValueBase,
                'revenue_variance' => $revenueVariance,
            ];
        }

        return $prepared;
    }

    /**
     * Prepare direct invoice lines (no Sales Order).
     */
    private function prepareDirectLines(array $lines, float $exchangeRate): array
    {
        if (empty($lines)) {
            return [];
        }

        $prepared = [];
        $lineNumber = 1;

        foreach ($lines as $payloadLine) {
            $quantity = (float) ($payloadLine['quantity'] ?? 0);
            $unitPrice = (float) ($payloadLine['unit_price'] ?? 0);
            $discountRate = (float) ($payloadLine['discount_rate'] ?? 0);
            $taxRate = (float) ($payloadLine['tax_rate'] ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            if ($unitPrice < 0) {
                throw new SalesInvoiceException('Harga satuan tidak boleh negatif.');
            }

            $grossTotal = $this->roundMoney($quantity * $unitPrice);
            $discountAmount = $this->roundMoney($grossTotal * ($discountRate / 100));
            $lineTotal = $this->roundMoney($grossTotal - $discountAmount);
            $taxAmount = $this->roundMoney($lineTotal * ($taxRate / 100));
            $lineTotalBase = $this->roundCost($lineTotal * $exchangeRate);

            $prepared[] = [
                'line_number' => $lineNumber++,
                'sales_order_line_id' => null,
                'sales_delivery_line_id' => null,
                'product_id' => $payloadLine['product_id'] ?? null,
                'product_variant_id' => $payloadLine['product_variant_id'] ?? null,
                'description' => $payloadLine['description'] ?? '',
                'uom_label' => $payloadLine['uom_label'] ?? null,
                'quantity' => $this->roundQuantity($quantity),
                'quantity_base' => $this->roundQuantity($quantity),
                'unit_price' => $unitPrice,
                'discount_rate' => $discountRate,
                'discount_amount' => $discountAmount,
                'tax_rate' => $taxRate,
                'tax_amount' => $this->roundMoney($taxAmount),
                'line_total' => $lineTotal,
                'line_total_base' => $lineTotalBase,
                'delivery_value_base' => 0.0,
                'revenue_variance' => 0.0,
            ];
        }

        return $prepared;
    }

    private function persistInvoiceLines(SalesInvoice $invoice, array $preparedLines, float $shippingCharge = 0.0): array
    {
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($preparedLines as $line) {
            $subtotal += $line['line_total'];
            $taxTotal += $line['tax_amount'];

            $invoice->lines()->create([
                'sales_order_line_id' => $line['sales_order_line_id'],
                'sales_delivery_line_id' => $line['sales_delivery_line_id'],
                'product_id' => $line['product_id'] ?? null,
                'product_variant_id' => $line['product_variant_id'] ?? null,
                'line_number' => $line['line_number'],
                'description' => $line['description'],
                'uom_label' => $line['uom_label'],
                'quantity' => $line['quantity'],
                'quantity_base' => $line['quantity_base'],
                'unit_price' => $line['unit_price'],
                'discount_rate' => $line['discount_rate'] ?? 0,
                'discount_amount' => $line['discount_amount'] ?? 0,
                'tax_rate' => $line['tax_rate'] ?? 0,
                'tax_amount' => $line['tax_amount'],
                'line_total' => $line['line_total'],
                'line_total_base' => $line['line_total_base'],
                'delivery_value_base' => $line['delivery_value_base'],
                'revenue_variance' => $line['revenue_variance'],
            ]);
        }

        return [
            'subtotal' => $this->roundMoney($subtotal),
            'tax_total' => $this->roundMoney($taxTotal),
            'total_amount' => $this->roundMoney($subtotal + $taxTotal + $shippingCharge),
        ];
    }

    private function preparePostingLines(SalesInvoice $invoice): array
    {
        $prepared = [];

        foreach ($invoice->lines as $line) {
            $deliveryLine = $line->salesDeliveryLine;
            $soLine = $line->salesOrderLine;

            if (! $deliveryLine || ! $soLine) {
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

    private function calculateTotals(array $lines, float $shippingCharge = 0.0): array
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
            'total_amount' => $this->roundMoney($subtotal + $taxTotal + $shippingCharge),
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

        $soNumbers = $invoice->salesOrders->pluck('order_number')->toArray();

        $meta = [
            'sales_order_numbers' => $soNumbers,
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
        $shippingBase = $this->roundCost((float) $invoice->shipping_charge * $exchangeRate);

        $entries = array_filter([
            AccountingEntry::debit('receivable', $invoiceBaseAmount),
            AccountingEntry::credit('revenue', $totals['delivery_value_base']),
            $totals['revenue_variance'] !== 0.0
                ? ($totals['revenue_variance'] > 0
                    ? AccountingEntry::credit('revenue_variance', abs($totals['revenue_variance']))
                    : AccountingEntry::debit('revenue_variance', abs($totals['revenue_variance'])))
                : null,
        ]);

        // Add shipping charge entries if applicable - both use GL Event Configuration accounts
        if ($shippingBase > 0) {
            $entries[] = AccountingEntry::debit('shipping_charge_receivable', $shippingBase);
            $entries[] = AccountingEntry::credit('shipping_charge_revenue', $shippingBase);
        }

        $payload->setLines($entries);

        rescue(function () use ($payload) {
            $this->accountingEventBus->dispatch($payload);
        }, static function (Throwable $throwable) {
            report($throwable);
        });
    }

    /**
     * Dispatch accounting event for direct invoice (no COGS/variance).
     */
    private function dispatchDirectArPostedEvent(SalesInvoice $invoice, ?Authenticatable $actor): void
    {
        $totalAmount = (float) $invoice->total_amount;
        if ($totalAmount <= 0) {
            return;
        }

        $currencyCode = $invoice->currency?->code ?? 'IDR';
        $occurredAt = CarbonImmutable::parse($invoice->invoice_date ?? now());
        $exchangeRate = (float) ($invoice->exchange_rate ?: 1);

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
            ['direct_invoice' => true]
        );

        $baseAmount = $this->roundCost($totalAmount * $exchangeRate);
        $taxBase = $this->roundCost((float) $invoice->tax_total * $exchangeRate);
        $shippingBase = $this->roundCost((float) $invoice->shipping_charge * $exchangeRate);
        $revenueBase = $this->roundCost((float) $invoice->subtotal * $exchangeRate);

        $entries = array_filter([
            AccountingEntry::debit('receivable', $baseAmount),
            AccountingEntry::credit('revenue', $revenueBase),
            $taxBase > 0 ? AccountingEntry::credit('tax_payable', $taxBase) : null,
        ]);

        // Add shipping charge entries if applicable - both use GL Event Configuration accounts
        if ($shippingBase > 0) {
            $entries[] = AccountingEntry::debit('shipping_charge_receivable', $shippingBase);
            $entries[] = AccountingEntry::credit('shipping_charge_revenue', $shippingBase);
        }

        $payload->setLines($entries);

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

        if (! in_array($salesOrder->status, [
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
        if (! in_array($salesOrder->status, [
            SalesOrderStatus::CONFIRMED->value,
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
            ->withoutGlobalScope('accessLevel')
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

    /**
     * Create an External Debt record for the posted Sales Invoice.
     * This integrates with the External Debt module for AR tracking.
     */
    private function createExternalDebt(SalesInvoice $invoice, ?Authenticatable $actor): void
    {
        $invoice->loadMissing(['branch.branchGroup.company', 'partner', 'currency']);

        $company = $invoice->branch?->branchGroup?->company;
        if (! $company) {
            return; // Cannot create external debt without company context
        }

        $debtAccountId = $company->default_receivable_account_id;
        if (! $debtAccountId) {
            return; // Cannot create external debt without a default receivable account
        }

        // For offset account, use revenue account based on company config
        $offsetAccountId = $company->default_revenue_account_id ?? $debtAccountId;

        $totalAmount = (float) $invoice->total_amount;
        $exchangeRate = (float) ($invoice->exchange_rate ?? 1);

        // Build notes with invoice information
        $notes = "Dibuat dari Faktur Penjualan: {$invoice->invoice_number}";
        if ($invoice->notes) {
            $notes .= "\n\n".$invoice->notes;
        }

        $externalDebt = ExternalDebt::create([
            'type' => 'receivable',
            'branch_id' => $invoice->branch_id,
            'partner_id' => $invoice->partner_id,
            'currency_id' => $invoice->currency_id,
            'exchange_rate' => $exchangeRate,
            'issue_date' => $invoice->invoice_date,
            'due_date' => $invoice->due_date,
            'amount' => $totalAmount,
            'primary_currency_amount' => $this->roundMoney($totalAmount * $exchangeRate),
            'offset_account_id' => $offsetAccountId,
            'debt_account_id' => $debtAccountId,
            'status' => 'open',
            'reference_number' => $invoice->customer_invoice_number,
            'notes' => $notes,
            'source_type' => SalesInvoice::class,
            'source_id' => $invoice->id,
            'created_by' => $actor?->getAuthIdentifier(),
        ]);

        // Link the external debt back to the invoice
        $invoice->update([
            'external_debt_id' => $externalDebt->id,
        ]);

        // Dispatch event for journal creation
        rescue(function () use ($externalDebt) {
            ExternalDebtCreated::dispatch($externalDebt);
        }, static function (Throwable $throwable) {
            report($throwable);
        });
    }

    /**
     * Persist costs for a sales invoice.
     */
    private function persistCosts(SalesInvoice $invoice, array $costs): void
    {
        // Delete existing costs for update case
        $invoice->costs()->delete();

        foreach ($costs as $cost) {
            if (! isset($cost['amount']) || (float) $cost['amount'] <= 0) {
                continue;
            }

            SalesInvoiceCost::create([
                'sales_invoice_id' => $invoice->id,
                'sales_order_cost_id' => $cost['sales_order_cost_id'] ?? null,
                'cost_item_id' => $cost['cost_item_id'] ?? null,
                'description' => $cost['description'] ?? null,
                'amount' => $cost['amount'],
                'currency_id' => $cost['currency_id'] ?? $invoice->currency_id,
                'exchange_rate' => $cost['exchange_rate'] ?? $invoice->exchange_rate ?? 1,
            ]);
        }
    }

    /**
     * Create journal entries for invoice direct costs using cost item accounts.
     */
    private function createCostEntries(SalesInvoice $invoice, ?Authenticatable $actor): void
    {
        $invoice->loadMissing(['costs.costItem', 'branch', 'currency']);

        if ($invoice->costs->isEmpty()) {
            return;
        }

        // Filter costs that have valid cost items with accounts
        $validCosts = $invoice->costs->filter(function ($cost) {
            return $cost->costItem
                && $cost->costItem->debit_account_id
                && $cost->costItem->credit_account_id
                && (float) $cost->amount > 0;
        });

        if ($validCosts->isEmpty()) {
            return;
        }

        // Create a single journal for all direct costs
        $journal = \App\Models\Journal::create([
            'branch_id' => $invoice->branch_id,
            'user_global_id' => $actor?->getAuthIdentifier() ?? 'system',
            'date' => $invoice->invoice_date,
            'journal_type' => 'sales',
            'reference_number' => $invoice->invoice_number,
            'description' => "Direct Costs - {$invoice->invoice_number}",
        ]);

        foreach ($validCosts as $invoiceCost) {
            $costItem = $invoiceCost->costItem;
            $amount = (float) $invoiceCost->amount;
            $primaryAmount = $amount * (float) $invoiceCost->exchange_rate;
            $description = $costItem->name.($invoiceCost->description ? ": {$invoiceCost->description}" : '');

            // Debit entry (expense account)
            $journal->journalEntries()->create([
                'account_id' => $costItem->debit_account_id,
                'debit' => $amount,
                'credit' => 0,
                'currency_id' => $invoiceCost->currency_id,
                'exchange_rate' => $invoiceCost->exchange_rate,
                'primary_currency_debit' => $primaryAmount,
                'primary_currency_credit' => 0,
            ]);

            // Credit entry (offset account)
            $journal->journalEntries()->create([
                'account_id' => $costItem->credit_account_id,
                'debit' => 0,
                'credit' => $amount,
                'currency_id' => $invoiceCost->currency_id,
                'exchange_rate' => $invoiceCost->exchange_rate,
                'primary_currency_debit' => 0,
                'primary_currency_credit' => $primaryAmount,
            ]);
        }
    }
}
