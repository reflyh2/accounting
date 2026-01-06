<?php

namespace App\Services\Purchasing;

use App\Domain\Accounting\DTO\AccountingEntry;
use App\Domain\Accounting\DTO\AccountingEventPayload;
use App\Enums\AccountingEventCode;
use App\Enums\Documents\GoodsReceiptStatus;
use App\Enums\Documents\PurchaseOrderStatus;
use App\Exceptions\PurchaseOrderException;
use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\Location;
use App\Models\Partner;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Uom;
use App\Services\Accounting\AccountingEventBus;
use App\Services\Inventory\DTO\ReceiptDTO;
use App\Services\Inventory\DTO\ReceiptLineDTO;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\UomConversionService;
use App\Services\Tax\TaxService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PurchaseService
{
    private const QTY_TOLERANCE = 0.0005;
    private const COST_SCALE = 6;

    public function __construct(
        private readonly UomConversionService $uomConverter,
        private readonly InventoryService $inventoryService,
        private readonly AccountingEventBus $accountingEventBus,
        private readonly TaxService $taxService,
    ) {
    }

    public function create(array $payload, ?Authenticatable $actor = null): PurchaseOrder
    {
        $actor ??= Auth::user();

        return DB::transaction(function () use ($payload, $actor) {
            $branch = Branch::with('branchGroup')->findOrFail($payload['branch_id']);
            $companyId = $branch->branchGroup?->company_id;

            if (!$companyId) {
                throw new PurchaseOrderException('Cabang tidak terhubung ke perusahaan manapun.');
            }

            $this->assertBranchBelongsToCompany($payload['company_id'] ?? null, $companyId);

            $supplier = $this->resolveSupplier((int) $payload['partner_id'], (int) $companyId);
            $orderDate = Carbon::parse($payload['order_date']);

            $purchaseOrder = PurchaseOrder::create([
                'company_id' => $companyId,
                'branch_id' => $branch->id,
                'partner_id' => $supplier->id,
                'currency_id' => $payload['currency_id'],
                'order_number' => $this->generateOrderNumber($companyId, $branch->id, $orderDate),
                'order_date' => $orderDate,
                'expected_date' => isset($payload['expected_date']) ? Carbon::parse($payload['expected_date']) : null,
                'supplier_reference' => $payload['supplier_reference'] ?? null,
                'payment_terms' => $payload['payment_terms'] ?? null,
                'exchange_rate' => $payload['exchange_rate'] ?? 1,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $actor?->getAuthIdentifier(),
            ]);

            $totals = $this->persistLines($purchaseOrder, $payload['lines'] ?? [], (int) $companyId);

            $purchaseOrder->update([
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total_amount' => $totals['total_amount'],
            ]);

            // Update ordered_qty on purchase plan lines if source_plan_line_id is set
            $this->updatePurchasePlanOrderedQuantities($purchaseOrder);

            return $purchaseOrder->fresh([
                'partner',
                'branch.branchGroup.company',
                'currency',
                'lines.variant',
                'lines.uom',
                'lines.baseUom',
            ]);
        });
    }

    public function update(PurchaseOrder $purchaseOrder, array $payload, ?Authenticatable $actor = null): PurchaseOrder
    {
        $this->assertDraft($purchaseOrder);

        $actor ??= Auth::user();

        return DB::transaction(function () use ($purchaseOrder, $payload, $actor) {
            $branch = Branch::with('branchGroup')->findOrFail($payload['branch_id']);
            $companyId = $branch->branchGroup?->company_id;

            if (!$companyId) {
                throw new PurchaseOrderException('Cabang tidak terhubung ke perusahaan manapun.');
            }

            $this->assertBranchBelongsToCompany($payload['company_id'] ?? null, $companyId);

            $supplier = $this->resolveSupplier((int) $payload['partner_id'], (int) $companyId);

            $purchaseOrder->update([
                'branch_id' => $branch->id,
                'partner_id' => $supplier->id,
                'currency_id' => $payload['currency_id'],
                'order_date' => Carbon::parse($payload['order_date']),
                'expected_date' => isset($payload['expected_date']) ? Carbon::parse($payload['expected_date']) : null,
                'supplier_reference' => $payload['supplier_reference'] ?? null,
                'payment_terms' => $payload['payment_terms'] ?? null,
                'exchange_rate' => $payload['exchange_rate'] ?? 1,
                'notes' => $payload['notes'] ?? null,
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            $purchaseOrder->lines()->delete();
            $totals = $this->persistLines($purchaseOrder, $payload['lines'] ?? [], (int) $companyId);

            $purchaseOrder->update([
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total_amount' => $totals['total_amount'],
            ]);

            return $purchaseOrder->fresh([
                'partner',
                'branch.branchGroup.company',
                'currency',
                'lines.variant',
                'lines.uom',
                'lines.baseUom',
            ]);
        });
    }

    public function delete(PurchaseOrder $purchaseOrder): void
    {
        $this->assertDraft($purchaseOrder);

        DB::transaction(function () use ($purchaseOrder): void {
            $purchaseOrder->lines()->delete();
            $purchaseOrder->delete();
        });
    }

    public function postGrn(PurchaseOrder $purchaseOrder, array $payload, ?Authenticatable $actor = null): GoodsReceipt
    {
        $actor ??= Auth::user();

        if (!in_array($purchaseOrder->status, [
            PurchaseOrderStatus::SENT->value,
            PurchaseOrderStatus::PARTIALLY_RECEIVED->value,
        ], true)) {
            throw new PurchaseOrderException('Purchase Order belum siap untuk diterima.');
        }

        $purchaseOrder->loadMissing([
            'lines.variant.product',
            'lines.uom',
            'lines.baseUom',
            'partner',
            'branch.branchGroup.company',
            'currency',
        ]);

        if (!$purchaseOrder->lines->count()) {
            throw new PurchaseOrderException('Purchase Order tidak memiliki detail barang.');
        }

        if (!isset($payload['location_id'])) {
            throw new PurchaseOrderException('Lokasi penerimaan wajib dipilih.');
        }

        if (!isset($payload['receipt_date'])) {
            throw new PurchaseOrderException('Tanggal penerimaan wajib diisi.');
        }

        $receiptDate = Carbon::parse($payload['receipt_date']);

        /** @var Location $location */
        $location = Location::with('branch.branchGroup.company')->findOrFail($payload['location_id']);

        if ((int) $location->branch_id !== (int) $purchaseOrder->branch_id) {
            throw new PurchaseOrderException('Lokasi penerimaan harus berasal dari cabang yang sama dengan Purchase Order.');
        }

        $valuationMethod = $payload['valuation_method']
            ?? config('inventory.default_valuation_method', 'fifo');

        $preparedLines = $this->prepareGoodsReceiptLines($purchaseOrder, $payload['lines'] ?? []);

        if (empty($preparedLines)) {
            throw new PurchaseOrderException('Minimal satu baris penerimaan harus diisi.');
        }

        return DB::transaction(function () use (
            $purchaseOrder,
            $preparedLines,
            $location,
            $receiptDate,
            $valuationMethod,
            $payload,
            $actor
        ) {
            $receiptNumber = $this->generateGoodsReceiptNumber(
                $purchaseOrder->company_id,
                $purchaseOrder->branch_id,
                $receiptDate
            );

            $goodsReceipt = GoodsReceipt::create([
                'purchase_order_id' => $purchaseOrder->id,
                'company_id' => $purchaseOrder->company_id,
                'branch_id' => $purchaseOrder->branch_id,
                'currency_id' => $purchaseOrder->currency_id,
                'location_id' => $location->id,
                'receipt_number' => $receiptNumber,
                'status' => GoodsReceiptStatus::DRAFT->value,
                'receipt_date' => $receiptDate,
                'valuation_method' => strtolower($valuationMethod),
                'exchange_rate' => $purchaseOrder->exchange_rate,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $actor?->getAuthIdentifier(),
            ]);

            $totalQuantityBase = 0.0;
            $totalValue = 0.0;
            $totalValueBase = 0.0;

            foreach ($preparedLines as $plan) {
                $line = $plan['line'];
                $goodsReceipt->lines()->create([
                    'purchase_order_line_id' => $line->id,
                    'product_id' => $line->product_id,
                    'product_variant_id' => $line->product_variant_id,
                    'description' => $line->description,
                    'uom_id' => $line->uom_id,
                    'base_uom_id' => $line->base_uom_id,
                    'quantity' => $plan['quantity'],
                    'quantity_base' => $plan['quantity_base'],
                    'unit_price' => $plan['unit_price'],
                    'unit_cost_base' => $plan['unit_cost_base'],
                    'line_total' => $plan['line_total'],
                    'line_total_base' => $plan['line_total_base'],
                ]);

                $line->quantity_received = $this->roundQuantity(
                    (float) $line->quantity_received + $plan['quantity']
                );
                $line->quantity_received_base = $this->roundQuantity(
                    (float) $line->quantity_received_base + $plan['quantity_base']
                );
                $line->save();

                $totalQuantityBase += $plan['quantity_base'];
                $totalValue += $plan['line_total'];
                $totalValueBase += $plan['line_total_base'];
            }

            $receiptDto = new ReceiptDTO(
                $receiptDate,
                $location->id,
                array_map(
                    fn (array $plan) => new ReceiptLineDTO(
                        $plan['line']->product_variant_id,
                        $plan['line']->base_uom_id,
                        $plan['quantity_base'],
                        $plan['unit_cost_base']
                    ),
                    $preparedLines
                ),
                sourceType: PurchaseOrder::class,
                sourceId: $purchaseOrder->id,
                notes: $payload['notes'] ?? null,
                valuationMethod: $valuationMethod,
            );

            $result = $this->inventoryService->receipt($receiptDto);

            $goodsReceipt->update([
                'inventory_transaction_id' => $result->transaction->id,
                'status' => GoodsReceiptStatus::POSTED->value,
                'posted_at' => now(),
                'posted_by' => $actor?->getAuthIdentifier(),
                'total_quantity' => $this->roundQuantity($totalQuantityBase),
                'total_value' => $this->roundMoney($totalValue),
                'total_value_base' => $this->roundCost($totalValueBase),
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            $purchaseOrder->load('lines');
            $this->syncPurchaseOrderReceiptStatus($purchaseOrder, $actor);

            $goodsReceipt->load([
                'purchaseOrder.partner',
                'purchaseOrder.branch.branchGroup.company',
                'lines.variant.product',
                'currency',
                'location',
                'inventoryTransaction',
            ]);

            $this->dispatchGoodsReceiptEvent($goodsReceipt, $purchaseOrder, $totalValueBase, $actor);

            return $goodsReceipt;
        });
    }

    /**
     * Create a goods receipt from multiple purchase orders.
     *
     * @param array<int> $purchaseOrderIds
     * @param array $payload
     * @param Authenticatable|null $actor
     * @return GoodsReceipt
     */
    public function createGoodsReceipt(array $purchaseOrderIds, array $payload, ?Authenticatable $actor = null): GoodsReceipt
    {
        $actor ??= Auth::user();

        if (empty($purchaseOrderIds)) {
            throw new PurchaseOrderException('Minimal satu Purchase Order harus dipilih.');
        }

        $purchaseOrders = PurchaseOrder::with([
            'lines.variant.product',
            'lines.uom',
            'lines.baseUom',
            'partner',
            'branch.branchGroup.company',
            'currency',
        ])->whereIn('id', $purchaseOrderIds)->get();

        if ($purchaseOrders->count() !== count($purchaseOrderIds)) {
            throw new PurchaseOrderException('Satu atau lebih Purchase Order tidak ditemukan.');
        }

        // Validate all POs have valid status
        foreach ($purchaseOrders as $po) {
            if (!in_array($po->status, [
                PurchaseOrderStatus::SENT->value,
                PurchaseOrderStatus::PARTIALLY_RECEIVED->value,
            ], true)) {
                throw new PurchaseOrderException("Purchase Order {$po->order_number} belum siap untuk diterima.");
            }
        }

        // Validate all POs belong to the same branch
        $branchIds = $purchaseOrders->pluck('branch_id')->unique();
        if ($branchIds->count() > 1) {
            throw new PurchaseOrderException('Semua Purchase Order harus berasal dari cabang yang sama.');
        }

        $firstPo = $purchaseOrders->first();

        if (!isset($payload['location_id'])) {
            throw new PurchaseOrderException('Lokasi penerimaan wajib dipilih.');
        }

        if (!isset($payload['receipt_date'])) {
            throw new PurchaseOrderException('Tanggal penerimaan wajib diisi.');
        }

        $receiptDate = Carbon::parse($payload['receipt_date']);

        /** @var Location $location */
        $location = Location::with('branch.branchGroup.company')->findOrFail($payload['location_id']);

        if ((int) $location->branch_id !== (int) $firstPo->branch_id) {
            throw new PurchaseOrderException('Lokasi penerimaan harus berasal dari cabang yang sama dengan Purchase Order.');
        }

        // Get valuation method from company's costing_policy
        $valuationMethod = $location->branch?->branchGroup?->company?->costing_policy
            ?? config('inventory.default_valuation_method', 'fifo');

        // Prepare lines from all POs
        $preparedLines = $this->prepareGoodsReceiptLinesFromMultiplePOs($purchaseOrders, $payload['lines'] ?? []);

        if (empty($preparedLines)) {
            throw new PurchaseOrderException('Minimal satu baris penerimaan harus diisi.');
        }

        return DB::transaction(function () use (
            $purchaseOrders,
            $firstPo,
            $preparedLines,
            $location,
            $receiptDate,
            $valuationMethod,
            $payload,
            $actor
        ) {
            $receiptNumber = $this->generateGoodsReceiptNumber(
                $firstPo->company_id,
                $firstPo->branch_id,
                $receiptDate
            );

            $goodsReceipt = GoodsReceipt::create([
                'purchase_order_id' => null, // Null for multi-PO receipts
                'supplier_id' => $payload['supplier_id'] ?? null,
                'company_id' => $firstPo->company_id,
                'branch_id' => $firstPo->branch_id,
                'currency_id' => $firstPo->currency_id,
                'location_id' => $location->id,
                'receipt_number' => $receiptNumber,
                'status' => GoodsReceiptStatus::DRAFT->value,
                'receipt_date' => $receiptDate,
                'valuation_method' => strtolower($valuationMethod),
                'exchange_rate' => $firstPo->exchange_rate,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $actor?->getAuthIdentifier(),
            ]);

            // Attach all purchase orders via pivot table
            $goodsReceipt->purchaseOrders()->attach($purchaseOrders->pluck('id'));

            $totalQuantityBase = 0.0;
            $totalValue = 0.0;
            $totalValueBase = 0.0;

            foreach ($preparedLines as $plan) {
                $line = $plan['line'];
                $goodsReceipt->lines()->create([
                    'purchase_order_line_id' => $line->id,
                    'product_id' => $line->product_id,
                    'product_variant_id' => $line->product_variant_id,
                    'description' => $line->description,
                    'uom_id' => $plan['uom_id'],
                    'base_uom_id' => $line->base_uom_id,
                    'quantity' => $plan['quantity'],
                    'quantity_base' => $plan['quantity_base'],
                    'unit_price' => $plan['unit_price'],
                    'unit_cost_base' => $plan['unit_cost_base'],
                    'line_total' => $plan['line_total'],
                    'line_total_base' => $plan['line_total_base'],
                    'lot_id' => $plan['lot_id'] ?? null,
                    'serial_id' => $plan['serial_id'] ?? null,
                ]);

                // Update PO line received quantity
                $line->quantity_received = $this->roundQuantity(
                    (float) $line->quantity_received + $plan['quantity']
                );
                $line->quantity_received_base = $this->roundQuantity(
                    (float) $line->quantity_received_base + $plan['quantity_base']
                );
                $line->save();

                $totalQuantityBase += $plan['quantity_base'];
                $totalValue += $plan['line_total'];
                $totalValueBase += $plan['line_total_base'];
            }

            // Create inventory transaction
            $receiptDto = new ReceiptDTO(
                $receiptDate,
                $location->id,
                array_map(
                    fn (array $plan) => new ReceiptLineDTO(
                        $plan['line']->product_variant_id,
                        $plan['line']->base_uom_id,
                        $plan['quantity_base'],
                        $plan['unit_cost_base']
                    ),
                    $preparedLines
                ),
                sourceType: GoodsReceipt::class,
                sourceId: $goodsReceipt->id,
                notes: $payload['notes'] ?? null,
                valuationMethod: $valuationMethod,
            );

            $result = $this->inventoryService->receipt($receiptDto);

            $goodsReceipt->update([
                'inventory_transaction_id' => $result->transaction->id,
                'status' => GoodsReceiptStatus::POSTED->value,
                'posted_at' => now(),
                'posted_by' => $actor?->getAuthIdentifier(),
                'total_quantity' => $this->roundQuantity($totalQuantityBase),
                'total_value' => $this->roundMoney($totalValue),
                'total_value_base' => $this->roundCost($totalValueBase),
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            // Sync all PO statuses
            foreach ($purchaseOrders as $po) {
                $po->load('lines');
                $this->syncPurchaseOrderReceiptStatus($po, $actor);
            }

            $goodsReceipt->load([
                'purchaseOrders.partner',
                'purchaseOrders.branch.branchGroup.company',
                'lines.variant.product',
                'currency',
                'location',
                'inventoryTransaction',
            ]);

            // Dispatch accounting event
            $this->dispatchGoodsReceiptEventMultiPO($goodsReceipt, $totalValueBase, $actor);

            return $goodsReceipt;
        });
    }

    /**
     * Update an existing goods receipt.
     */
    public function updateGoodsReceipt(GoodsReceipt $goodsReceipt, array $payload, ?Authenticatable $actor = null): GoodsReceipt
    {
        $actor ??= Auth::user();

        $goodsReceipt->loadMissing([
            'lines.purchaseOrderLine',
            'purchaseOrders.lines',
            'inventoryTransaction',
            'location.branch.branchGroup.company',
        ]);

        if (!isset($payload['location_id'])) {
            throw new PurchaseOrderException('Lokasi penerimaan wajib dipilih.');
        }

        if (!isset($payload['receipt_date'])) {
            throw new PurchaseOrderException('Tanggal penerimaan wajib diisi.');
        }

        $receiptDate = Carbon::parse($payload['receipt_date']);

        /** @var Location $location */
        $location = Location::with('branch.branchGroup.company')->findOrFail($payload['location_id']);

        if ((int) $location->branch_id !== (int) $goodsReceipt->branch_id) {
            throw new PurchaseOrderException('Lokasi penerimaan harus berasal dari cabang yang sama.');
        }

        // Get valuation method from company's costing_policy
        $valuationMethod = $location->branch?->branchGroup?->company?->costing_policy
            ?? config('inventory.default_valuation_method', 'fifo');

        return DB::transaction(function () use (
            $goodsReceipt,
            $location,
            $receiptDate,
            $valuationMethod,
            $payload,
            $actor
        ) {
            // Reverse existing inventory transaction if posted
            if ($goodsReceipt->inventory_transaction_id && $goodsReceipt->inventoryTransaction) {
                // First, reverse the PO line received quantities
                foreach ($goodsReceipt->lines as $grnLine) {
                    if ($grnLine->purchaseOrderLine) {
                        $poLine = $grnLine->purchaseOrderLine;
                        $poLine->quantity_received = $this->roundQuantity(
                            max(0, (float) $poLine->quantity_received - (float) $grnLine->quantity)
                        );
                        $poLine->quantity_received_base = $this->roundQuantity(
                            max(0, (float) $poLine->quantity_received_base - (float) $grnLine->quantity_base)
                        );
                        $poLine->save();
                    }
                }

                // Set the inventory transaction foreign key on goods receipt to null
                $goodsReceipt->inventory_transaction_id = null;
                $goodsReceipt->save();

                // Reverse accounting entries if previously posted
                if ($goodsReceipt->status === GoodsReceiptStatus::POSTED->value) {
                    $this->dispatchGoodsReceiptReversalEvent($goodsReceipt, $actor);
                }

                // Delete the inventory transaction (reverses inventory)
                $this->inventoryService->deleteTransaction($goodsReceipt->inventoryTransaction);
            }

            // Delete existing GRN lines
            $goodsReceipt->lines()->delete();

            // Get all related POs
            $purchaseOrders = $goodsReceipt->purchaseOrders()->with([
                'lines.variant.product',
                'lines.uom',
                'lines.baseUom',
            ])->get();

            // If no POs via pivot, try the legacy single PO relationship
            if ($purchaseOrders->isEmpty() && $goodsReceipt->purchase_order_id) {
                $purchaseOrders = collect([
                    PurchaseOrder::with([
                        'lines.variant.product',
                        'lines.uom',
                        'lines.baseUom',
                    ])->find($goodsReceipt->purchase_order_id)
                ])->filter();
            }

            // Prepare new lines
            $preparedLines = $this->prepareGoodsReceiptLinesFromMultiplePOs($purchaseOrders, $payload['lines'] ?? []);

            if (empty($preparedLines)) {
                throw new PurchaseOrderException('Minimal satu baris penerimaan harus diisi.');
            }

            $totalQuantityBase = 0.0;
            $totalValue = 0.0;
            $totalValueBase = 0.0;

            foreach ($preparedLines as $plan) {
                $line = $plan['line'];
                $goodsReceipt->lines()->create([
                    'purchase_order_line_id' => $line->id,
                    'product_id' => $line->product_id,
                    'product_variant_id' => $line->product_variant_id,
                    'description' => $line->description,
                    'uom_id' => $plan['uom_id'],
                    'base_uom_id' => $line->base_uom_id,
                    'quantity' => $plan['quantity'],
                    'quantity_base' => $plan['quantity_base'],
                    'unit_price' => $plan['unit_price'],
                    'unit_cost_base' => $plan['unit_cost_base'],
                    'line_total' => $plan['line_total'],
                    'line_total_base' => $plan['line_total_base'],
                    'lot_id' => $plan['lot_id'],
                    'serial_id' => $plan['serial_id'],
                ]);

                // Update PO line received quantity
                $line->quantity_received = $this->roundQuantity(
                    (float) $line->quantity_received + $plan['quantity']
                );
                $line->quantity_received_base = $this->roundQuantity(
                    (float) $line->quantity_received_base + $plan['quantity_base']
                );
                $line->save();

                $totalQuantityBase += $plan['quantity_base'];
                $totalValue += $plan['line_total'];
                $totalValueBase += $plan['line_total_base'];
            }

            // Update basic fields
            $goodsReceipt->update([
                'location_id' => $location->id,
                'receipt_date' => $receiptDate,
                'valuation_method' => strtolower($valuationMethod),
                'notes' => $payload['notes'] ?? null,
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            // Create new inventory transaction
            $receiptDto = new ReceiptDTO(
                $receiptDate,
                $location->id,
                array_map(
                    fn (array $plan) => new ReceiptLineDTO(
                        $plan['line']->product_variant_id,
                        $plan['line']->base_uom_id,
                        $plan['quantity_base'],
                        $plan['unit_cost_base']
                    ),
                    $preparedLines
                ),
                sourceType: GoodsReceipt::class,
                sourceId: $goodsReceipt->id,
                notes: $payload['notes'] ?? null,
                valuationMethod: $valuationMethod,
            );

            $result = $this->inventoryService->receipt($receiptDto);

            $goodsReceipt->update([
                'inventory_transaction_id' => $result->transaction->id,
                'status' => GoodsReceiptStatus::POSTED->value,
                'posted_at' => now(),
                'posted_by' => $actor?->getAuthIdentifier(),
                'total_quantity' => $this->roundQuantity($totalQuantityBase),
                'total_value' => $this->roundMoney($totalValue),
                'total_value_base' => $this->roundCost($totalValueBase),
            ]);

            // Sync all PO statuses
            foreach ($purchaseOrders as $po) {
                $po->load('lines');
                $this->syncPurchaseOrderReceiptStatus($po, $actor);
            }

            $goodsReceipt->load([
                'purchaseOrders.partner',
                'purchaseOrders.branch.branchGroup.company',
                'lines.variant.product',
                'currency',
                'location',
                'inventoryTransaction',
            ]);

            // Dispatch accounting event for the updated receipt
            $this->dispatchGoodsReceiptEventMultiPO($goodsReceipt, $totalValueBase, $actor);

            return $goodsReceipt;
        });
    }

    /**
     * Delete a goods receipt, reversing inventory and accounting entries.
     */
    public function deleteGoodsReceipt(GoodsReceipt $goodsReceipt, ?Authenticatable $actor = null): void
    {
        $actor ??= Auth::user();

        $goodsReceipt->loadMissing([
            'lines.purchaseOrderLine',
            'purchaseOrders',
            'inventoryTransaction',
            'location.branch.branchGroup.company',
        ]);

        DB::transaction(function () use ($goodsReceipt, $actor) {
            // If posted, reverse inventory and accounting
            if ($goodsReceipt->status === GoodsReceiptStatus::POSTED->value) {
                // Reverse inventory transaction
                // Reverse inventory transaction
                if ($goodsReceipt->inventoryTransaction) {
                    $transaction = $goodsReceipt->inventoryTransaction;
                    $goodsReceipt->inventory_transaction_id = null;
                    $goodsReceipt->save();
                    
                    $this->inventoryService->deleteTransaction($transaction);
                }

                // Reverse accounting entries
                $this->dispatchGoodsReceiptReversalEvent($goodsReceipt, $actor);
            }

            // Restore PO line received quantities
            foreach ($goodsReceipt->lines as $grLine) {
                if ($grLine->purchaseOrderLine) {
                    $poLine = $grLine->purchaseOrderLine;
                    $poLine->quantity_received = $this->roundQuantity(
                        max(0, (float) $poLine->quantity_received - (float) $grLine->quantity)
                    );
                    $poLine->quantity_received_base = $this->roundQuantity(
                        max(0, (float) $poLine->quantity_received_base - (float) $grLine->quantity_base)
                    );
                    $poLine->save();
                }
            }

            // Update PO statuses
            foreach ($goodsReceipt->purchaseOrders as $po) {
                $po->loadMissing('lines');
                $this->updatePurchaseOrderStatus($po);
            }

            // Detach from pivot table
            $goodsReceipt->purchaseOrders()->detach();

            // Delete lines
            $goodsReceipt->lines()->delete();

            // Delete the goods receipt
            $goodsReceipt->delete();
        });
    }

    /**
     * Dispatch accounting reversal event for goods receipt deletion.
     */
    private function dispatchGoodsReceiptReversalEvent(GoodsReceipt $goodsReceipt, ?Authenticatable $actor = null): void
    {
        $amountBase = (float) $goodsReceipt->total_value_base;

        if ($amountBase <= 0) {
            return;
        }

        $currencyCode = $goodsReceipt->currency?->code ?? 'IDR';
        $purchaseOrderNumbers = $goodsReceipt->purchaseOrders->pluck('order_number')->toArray();

        $payload = new AccountingEventPayload(
            AccountingEventCode::PURCHASE_GRN_REVERSED,
            $goodsReceipt->company_id,
            $goodsReceipt->branch_id,
            'goods_receipt',
            $goodsReceipt->id,
            $goodsReceipt->receipt_number,
            $currencyCode,
            (float) $goodsReceipt->exchange_rate,
            CarbonImmutable::parse($goodsReceipt->receipt_date ?? now()),
            $actor?->getAuthIdentifier(),
            [
                'purchase_order_numbers' => $purchaseOrderNumbers,
                'reversal' => true,
            ],
        );

        $normalizedAmount = $this->roundCost($amountBase);

        // Reverse the original entries: credit inventory, debit GRNI
        $payload->setLines([
            AccountingEntry::debit('goods_received_not_invoiced', $normalizedAmount),
            AccountingEntry::credit('inventory', $normalizedAmount),
        ]);

        rescue(function () use ($payload) {
            $this->accountingEventBus->dispatch($payload);
        }, static function (Throwable $throwable) {
            report($throwable);
        });
    }

    /**
     * Update PO status based on received quantities.
     */
    private function updatePurchaseOrderStatus(PurchaseOrder $purchaseOrder): void
    {
        $totalReceived = $purchaseOrder->lines->sum('quantity_received');
        $totalOrdered = $purchaseOrder->lines->sum('quantity');

        if ($totalReceived <= 0) {
            // No received quantity, reset to SENT
            if ($purchaseOrder->status !== PurchaseOrderStatus::SENT->value) {
                $purchaseOrder->status = PurchaseOrderStatus::SENT->value;
                $purchaseOrder->save();
            }
        } elseif ($totalReceived >= $totalOrdered) {
            // Fully received
            if ($purchaseOrder->status !== PurchaseOrderStatus::RECEIVED->value) {
                $purchaseOrder->status = PurchaseOrderStatus::RECEIVED->value;
                $purchaseOrder->save();
            }
        } else {
            // Partially received
            if ($purchaseOrder->status !== PurchaseOrderStatus::PARTIALLY_RECEIVED->value) {
                $purchaseOrder->status = PurchaseOrderStatus::PARTIALLY_RECEIVED->value;
                $purchaseOrder->save();
            }
        }
    }

    public function approve(PurchaseOrder $purchaseOrder, ?Authenticatable $actor = null): PurchaseOrder
    {
        $actor ??= Auth::user();

        $purchaseOrder->transitionTo(
            PurchaseOrderStatus::APPROVED,
            $actor,
            $this->makerCheckerContext($purchaseOrder->company_id)
        );

        $purchaseOrder->update([
            'approved_by' => $actor?->getAuthIdentifier(),
            'approved_at' => now(),
        ]);

        return $purchaseOrder->refresh();
    }

    public function send(PurchaseOrder $purchaseOrder, ?Authenticatable $actor = null): PurchaseOrder
    {
        $actor ??= Auth::user();

        $purchaseOrder->transitionTo(
            PurchaseOrderStatus::SENT,
            $actor,
            $this->makerCheckerContext($purchaseOrder->company_id)
        );

        $purchaseOrder->update([
            'sent_by' => $actor?->getAuthIdentifier(),
            'sent_at' => now(),
        ]);

        return $purchaseOrder->refresh();
    }

    public function cancel(PurchaseOrder $purchaseOrder, ?Authenticatable $actor = null, ?string $reason = null): PurchaseOrder
    {
        $actor ??= Auth::user();

        $purchaseOrder->transitionTo(
            PurchaseOrderStatus::CANCELED,
            $actor,
            $this->makerCheckerContext($purchaseOrder->company_id)
        );

        $purchaseOrder->update([
            'canceled_by' => $actor?->getAuthIdentifier(),
            'canceled_at' => now(),
            'canceled_reason' => $reason,
        ]);

        return $purchaseOrder->refresh();
    }

    public function close(PurchaseOrder $purchaseOrder, ?Authenticatable $actor = null): PurchaseOrder
    {
        $actor ??= Auth::user();

        $purchaseOrder->transitionTo(
            PurchaseOrderStatus::CLOSED,
            $actor,
            $this->makerCheckerContext($purchaseOrder->company_id)
        );

        $purchaseOrder->update([
            'closed_by' => $actor?->getAuthIdentifier(),
            'closed_at' => now(),
        ]);

        return $purchaseOrder->refresh();
    }

    public function allowedStatuses(PurchaseOrder $purchaseOrder, ?Authenticatable $actor = null): array
    {
        $actor ??= Auth::user();

        return collect(
            $purchaseOrder->allowedDocumentStatuses(
                $actor,
                $this->makerCheckerContext($purchaseOrder->company_id)
            )
        )->map(fn (PurchaseOrderStatus $status) => $status->value)
            ->values()
            ->toArray();
    }

    public function shouldEnforceMakerChecker(?int $companyId): bool
    {
        unset($companyId); // Placeholder for future per-company overrides.
        return (bool) config('purchasing.maker_checker.enforce', false);
    }

    private function makerCheckerContext(?int $companyId): array
    {
        return [
            'enforceMakerChecker' => $this->shouldEnforceMakerChecker($companyId),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @return array{subtotal: float, tax_total: float, total_amount: float}
     */
    private function persistLines(PurchaseOrder $purchaseOrder, array $lines, int $companyId): array
    {
        if (empty($lines)) {
            throw new PurchaseOrderException('Minimal satu baris barang harus diisi.');
        }

        $lineNumber = 1;
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($lines as $line) {
            $variant = ProductVariant::with([
                'product.companies:id',
                'product.taxCategory',
                'product',
                'uom',
            ])->findOrFail($line['product_variant_id']);

            if (!$variant->product->companies->pluck('id')->contains($companyId)) {
                throw new PurchaseOrderException('Produk tidak tersedia untuk perusahaan ini.');
            }

            $orderedUom = Uom::findOrFail($line['uom_id']);
            if ((int) $orderedUom->company_id !== $companyId) {
                throw new PurchaseOrderException('Satuan tidak valid untuk perusahaan ini.');
            }

            $baseUom = $variant->uom;
            if ((int) $baseUom->company_id !== $companyId) {
                throw new PurchaseOrderException('Satuan dasar varian produk tidak valid.');
            }

            $quantity = $this->roundQuantity((float) $line['quantity']);
            $unitPrice = $this->roundMoney((float) $line['unit_price']);
            
            // Auto-resolve tax rate from product's tax category if not explicitly provided
            $taxRate = $this->resolveTaxRate(
                $variant,
                [
                    'company_id' => $companyId,
                    'partner_id' => $purchaseOrder->partner_id,
                    'date' => $purchaseOrder->order_date,
                ],
                $line['tax_rate'] ?? null
            );

            try {
                $quantityBase = $this->uomConverter->convert($quantity, $orderedUom->id, $baseUom->id);
            } catch (RuntimeException $exception) {
                throw new PurchaseOrderException($exception->getMessage(), previous: $exception);
            }

            $lineSubtotal = $this->roundMoney($quantity * $unitPrice);
            $lineTax = $this->roundMoney($lineSubtotal * ($taxRate / 100));
            $lineTotal = $this->roundMoney($lineSubtotal + $lineTax);

            $purchaseOrder->lines()->create([
                'line_number' => $lineNumber++,
                'source_plan_line_id' => $line['source_plan_line_id'] ?? null,
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'description' => $line['description'] ?? $variant->product->name,
                'uom_id' => $orderedUom->id,
                'base_uom_id' => $baseUom->id,
                'quantity' => $quantity,
                'quantity_base' => $quantityBase,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'tax_amount' => $lineTax,
                'line_total' => $lineTotal,
                'expected_date' => isset($line['expected_date']) ? Carbon::parse($line['expected_date']) : null,
            ]);

            $subtotal += $lineSubtotal;
            $taxTotal += $lineTax;
        }

        return [
            'subtotal' => $this->roundMoney($subtotal),
            'tax_total' => $this->roundMoney($taxTotal),
            'total_amount' => $this->roundMoney($subtotal + $taxTotal),
        ];
    }

    /**
     * @return array<int, array{
     *     line: \App\Models\PurchaseOrderLine,
     *     quantity: float,
     *     quantity_base: float,
     *     unit_price: float,
     *     unit_cost_base: float,
     *     line_total: float,
     *     line_total_base: float,
     * }>
     */
    private function prepareGoodsReceiptLines(PurchaseOrder $purchaseOrder, array $linesPayload): array
    {
        $prepared = [];
        $lines = $purchaseOrder->lines->keyBy('id');

        foreach ($linesPayload as $payloadLine) {
            if (!isset($payloadLine['purchase_order_line_id'])) {
                continue;
            }

            $quantity = (float) ($payloadLine['quantity'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            $lineId = (int) $payloadLine['purchase_order_line_id'];
            $line = $lines->get($lineId);

            if (!$line) {
                throw new PurchaseOrderException('Baris penerimaan tidak valid.');
            }

            $remaining = max(0.0, (float) $line->quantity - (float) $line->quantity_received);
            if ($quantity - $remaining > self::QTY_TOLERANCE) {
                throw new PurchaseOrderException(
                    sprintf('Jumlah diterima melebihi sisa pada baris #%d.', $line->line_number)
                );
            }

            try {
                $quantityBase = $this->roundQuantity(
                    $this->uomConverter->convert($quantity, $line->uom_id, $line->base_uom_id)
                );
            } catch (RuntimeException $exception) {
                throw new PurchaseOrderException($exception->getMessage(), previous: $exception);
            }

            $remainingBase = max(0.0, (float) $line->quantity_base - (float) $line->quantity_received_base);
            if ($quantityBase - $remainingBase > self::QTY_TOLERANCE) {
                throw new PurchaseOrderException(
                    sprintf('Jumlah dasar melebihi sisa pada baris #%d.', $line->line_number)
                );
            }

            $unitCostBase = (float) $line->unit_price;
            if ((float) $line->quantity_base > 0 && (float) $line->quantity > 0) {
                $unitCostBase = $line->unit_price * ((float) $line->quantity / (float) $line->quantity_base);
            }

            $unitCostBase = $this->roundCost($unitCostBase);
            $orderedQuantity = $this->roundQuantity($quantity);
            $lineTotal = $this->roundMoney($orderedQuantity * (float) $line->unit_price);
            $lineTotalBase = $this->roundCost($quantityBase * $unitCostBase);

            $prepared[] = [
                'line' => $line,
                'quantity' => $orderedQuantity,
                'quantity_base' => $quantityBase,
                'unit_price' => (float) $line->unit_price,
                'unit_cost_base' => $unitCostBase,
                'line_total' => $lineTotal,
                'line_total_base' => $lineTotalBase,
            ];
        }

        return $prepared;
    }

    /**
     * Prepare GRN lines from multiple POs with optional custom UOM selection.
     *
     * @param \Illuminate\Support\Collection<int, PurchaseOrder> $purchaseOrders
     * @param array $linesPayload
     * @return array
     */
    private function prepareGoodsReceiptLinesFromMultiplePOs($purchaseOrders, array $linesPayload): array
    {
        $prepared = [];

        // Build a combined lookup of all PO lines from all POs
        $allLines = collect();
        foreach ($purchaseOrders as $po) {
            foreach ($po->lines as $line) {
                $allLines->put($line->id, $line);
            }
        }

        foreach ($linesPayload as $payloadLine) {
            if (!isset($payloadLine['purchase_order_line_id'])) {
                continue;
            }

            $quantity = (float) ($payloadLine['quantity'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            $lineId = (int) $payloadLine['purchase_order_line_id'];
            $line = $allLines->get($lineId);

            if (!$line) {
                throw new PurchaseOrderException('Baris penerimaan tidak valid.');
            }

            // Determine which UOM to use: custom from payload or PO line's UOM
            $selectedUomId = isset($payloadLine['uom_id']) && $payloadLine['uom_id']
                ? (int) $payloadLine['uom_id']
                : $line->uom_id;

            // Validate selected UOM if different from PO UOM
            if ($selectedUomId !== $line->uom_id) {
                $selectedUom = Uom::find($selectedUomId);
                if (!$selectedUom) {
                    throw new PurchaseOrderException('Satuan yang dipilih tidak valid.');
                }
            }

            // Convert quantity from selected UOM to base UOM
            try {
                $quantityBase = $this->roundQuantity(
                    $this->uomConverter->convert($quantity, $selectedUomId, $line->base_uom_id)
                );
            } catch (RuntimeException $exception) {
                throw new PurchaseOrderException($exception->getMessage(), previous: $exception);
            }

            // Validate against remaining base quantity
            $remainingBase = max(0.0, (float) $line->quantity_base - (float) $line->quantity_received_base);
            if ($quantityBase - $remainingBase > self::QTY_TOLERANCE) {
                throw new PurchaseOrderException(
                    sprintf('Jumlah dasar melebihi sisa pada baris #%d.', $line->line_number)
                );
            }

            // Calculate unit cost in base UOM (from PO line's pricing)
            $unitCostBase = (float) $line->unit_price;
            if ((float) $line->quantity_base > 0 && (float) $line->quantity > 0) {
                $unitCostBase = $line->unit_price * ((float) $line->quantity / (float) $line->quantity_base);
            }
            $unitCostBase = $this->roundCost($unitCostBase);

            // Calculate unit price in selected UOM
            // If UOM changed, recalculate unit price based on conversion
            $unitPriceInSelectedUom = (float) $line->unit_price;
            if ($selectedUomId !== $line->uom_id) {
                try {
                    // Calculate conversion factor from PO UOM to selected UOM
                    $conversionFactor = $this->uomConverter->convert(1, $line->uom_id, $selectedUomId);
                    $unitPriceInSelectedUom = $this->roundMoney((float) $line->unit_price * $conversionFactor);
                } catch (RuntimeException $exception) {
                    // If conversion fails, use original unit price
                    $unitPriceInSelectedUom = (float) $line->unit_price;
                }
            }

            $orderedQuantity = $this->roundQuantity($quantity);
            $lineTotal = $this->roundMoney($orderedQuantity * $unitPriceInSelectedUom);
            $lineTotalBase = $this->roundCost($quantityBase * $unitCostBase);

            $prepared[] = [
                'line' => $line,
                'quantity' => $orderedQuantity,
                'quantity_base' => $quantityBase,
                'uom_id' => $selectedUomId,
                'unit_price' => $unitPriceInSelectedUom,
                'unit_cost_base' => $unitCostBase,
                'line_total' => $lineTotal,
                'line_total_base' => $lineTotalBase,
                'lot_id' => $payloadLine['lot_id'] ?? null,
                'serial_id' => $payloadLine['serial_id'] ?? null,
            ];
        }

        return $prepared;
    }

    private function generateGoodsReceiptNumber(int $companyId, int $branchId, Carbon $receiptDate): string
    {
        $config = config('purchasing.goods_receipt_numbering', []);
        $prefix = strtoupper($config['prefix'] ?? 'GRN');
        $sequencePadding = (int) ($config['sequence_padding'] ?? 5);
        $sequence = str_pad(
            (string) $this->nextGoodsReceiptSequence($branchId, $receiptDate),
            $sequencePadding,
            '0',
            STR_PAD_LEFT
        );

        $companySegment = str_pad((string) $companyId, 2, '0', STR_PAD_LEFT);
        $branchSegment = str_pad((string) $branchId, 3, '0', STR_PAD_LEFT);
        $yearSegment = $receiptDate->format('y');

        return sprintf('%s.%s%s.%s.%s', $prefix, $companySegment, $branchSegment, $yearSegment, $sequence);
    }

    private function nextGoodsReceiptSequence(int $branchId, Carbon $receiptDate): int
    {
        $latest = GoodsReceipt::withTrashed()
            ->where('branch_id', $branchId)
            ->whereYear('receipt_date', $receiptDate->year)
            ->orderByDesc('receipt_number')
            ->value('receipt_number');

        if (!$latest) {
            return 1;
        }

        $segments = explode('.', $latest);
        $last = (int) (end($segments) ?: 0);

        return $last + 1;
    }

    private function roundCost(float $value): float
    {
        return round($value, self::COST_SCALE);
    }

    private function syncPurchaseOrderReceiptStatus(PurchaseOrder $purchaseOrder, ?Authenticatable $actor = null): void
    {
        $actor ??= Auth::user();
        $context = $this->makerCheckerContext($purchaseOrder->company_id);

        $hasRemaining = $purchaseOrder->lines->contains(function ($line) {
            return ((float) $line->quantity - (float) $line->quantity_received) > self::QTY_TOLERANCE;
        });

        $target = $hasRemaining
            ? PurchaseOrderStatus::PARTIALLY_RECEIVED
            : PurchaseOrderStatus::RECEIVED;

        $purchaseOrder->transitionTo($target, $actor, $context);
    }

    private function dispatchGoodsReceiptEvent(
        GoodsReceipt $goodsReceipt,
        PurchaseOrder $purchaseOrder,
        float $amountBase,
        ?Authenticatable $actor = null
    ): void {
        if ($amountBase <= 0) {
            return;
        }

        $currencyCode = $purchaseOrder->currency?->code ?? 'IDR';

        $payload = new AccountingEventPayload(
            AccountingEventCode::PURCHASE_GRN_POSTED,
            $purchaseOrder->company_id,
            $purchaseOrder->branch_id,
            'goods_receipt',
            $goodsReceipt->id,
            $goodsReceipt->receipt_number,
            $currencyCode,
            (float) $purchaseOrder->exchange_rate,
            CarbonImmutable::parse($goodsReceipt->receipt_date),
            $actor?->getAuthIdentifier(),
            [
                'purchase_order_id' => $purchaseOrder->id,
                'purchase_order_number' => $purchaseOrder->order_number,
                'inventory_transaction_id' => $goodsReceipt->inventory_transaction_id,
            ],
        );

        $normalizedAmount = $this->roundCost($amountBase);

        $payload->setLines([
            AccountingEntry::debit('inventory', $normalizedAmount),
            AccountingEntry::credit('goods_received_not_invoiced', $normalizedAmount),
        ]);

        rescue(function () use ($payload) {
            $this->accountingEventBus->dispatch($payload);
        }, static function (Throwable $throwable) {
            report($throwable);
        });
    }

    private function dispatchGoodsReceiptEventMultiPO(
        GoodsReceipt $goodsReceipt,
        float $amountBase,
        ?Authenticatable $actor = null
    ): void {
        if ($amountBase <= 0) {
            return;
        }

        $currencyCode = $goodsReceipt->currency?->code ?? 'IDR';
        $purchaseOrderIds = $goodsReceipt->purchaseOrders->pluck('id')->toArray();
        $purchaseOrderNumbers = $goodsReceipt->purchaseOrders->pluck('order_number')->toArray();

        $payload = new AccountingEventPayload(
            AccountingEventCode::PURCHASE_GRN_POSTED,
            $goodsReceipt->company_id,
            $goodsReceipt->branch_id,
            'goods_receipt',
            $goodsReceipt->id,
            $goodsReceipt->receipt_number,
            $currencyCode,
            (float) $goodsReceipt->exchange_rate,
            CarbonImmutable::parse($goodsReceipt->receipt_date),
            $actor?->getAuthIdentifier(),
            [
                'purchase_order_ids' => $purchaseOrderIds,
                'purchase_order_numbers' => $purchaseOrderNumbers,
                'inventory_transaction_id' => $goodsReceipt->inventory_transaction_id,
            ],
        );

        $normalizedAmount = $this->roundCost($amountBase);

        $payload->setLines([
            AccountingEntry::debit('inventory', $normalizedAmount),
            AccountingEntry::credit('goods_received_not_invoiced', $normalizedAmount),
        ]);

        rescue(function () use ($payload) {
            $this->accountingEventBus->dispatch($payload);
        }, static function (Throwable $throwable) {
            report($throwable);
        });
    }

    private function resolveSupplier(int $partnerId, int $companyId): Partner
    {
        /** @var Partner $partner */
        $partner = Partner::with(['roles', 'companies:id'])
            ->findOrFail($partnerId);

        $isSupplier = $partner->roles->pluck('role')->contains('supplier');
        $belongsToCompany = $partner->companies->pluck('id')->contains($companyId);

        if (!$isSupplier) {
            throw new PurchaseOrderException('Partner terpilih bukan supplier.');
        }

        if (!$belongsToCompany) {
            throw new PurchaseOrderException('Supplier tidak terdaftar pada perusahaan ini.');
        }

        return $partner;
    }

    private function assertDraft(PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::DRAFT->value) {
            throw new PurchaseOrderException('Hanya PO berstatus draft yang dapat diubah.');
        }
    }

    private function generateOrderNumber(int $companyId, int $branchId, Carbon $orderDate): string
    {
        $prefix = strtoupper(config('purchasing.numbering.prefix', 'PO'));
        $companySegment = str_pad((string) $companyId, 2, '0', STR_PAD_LEFT);
        $branchSegment = str_pad((string) $branchId, 3, '0', STR_PAD_LEFT);
        $yearSegment = $orderDate->format('y');
        $sequence = str_pad(
            (string) $this->nextSequence($branchId, $orderDate),
            (int) config('purchasing.numbering.sequence_padding', 5),
            '0',
            STR_PAD_LEFT
        );

        return sprintf('%s.%s%s.%s.%s', $prefix, $companySegment, $branchSegment, $yearSegment, $sequence);
    }

    private function nextSequence(int $branchId, Carbon $orderDate): int
    {
        $latest = PurchaseOrder::withTrashed()
            ->where('branch_id', $branchId)
            ->whereYear('order_date', $orderDate->year)
            ->orderByDesc('order_number')
            ->value('order_number');

        if (!$latest) {
            return 1;
        }

        $segments = explode('.', $latest);
        $last = (int) (end($segments) ?: 0);

        return $last + 1;
    }

    private function roundMoney(float $value): float
    {
        return round($value, 2);
    }

    private function roundQuantity(float $value): float
    {
        return round($value, 3);
    }

    private function assertBranchBelongsToCompany(?int $requestedCompanyId, int $branchCompanyId): void
    {
        if (!$requestedCompanyId) {
            throw new PurchaseOrderException('Perusahaan wajib dipilih.');
        }

        if ((int) $requestedCompanyId !== (int) $branchCompanyId) {
            throw new PurchaseOrderException('Cabang tidak sesuai dengan perusahaan yang dipilih.');
        }
    }

    /**
     * Resolve tax rate for a product variant.
     * Uses TaxService to get rate from tax rules if no explicit rate provided.
     */
    private function resolveTaxRate(
        ProductVariant $variant,
        array $context,
        ?float $explicitRate
    ): float {
        // If explicit rate provided, use it
        if ($explicitRate !== null) {
            return round($explicitRate, 2);
        }

        // Auto-resolve from product's tax category via TaxService
        $quote = $this->taxService->quote(
            $variant->product,
            $context
        );

        return round((float) ($quote['rate'] ?? 0), 2);
    }

    /**
     * Update ordered_qty on purchase plan lines when PO lines are linked to plans.
     *
     * @param PurchaseOrder $purchaseOrder
     * @return void
     */
    private function updatePurchasePlanOrderedQuantities(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->loadMissing('lines');

        $planLineUpdates = [];

        foreach ($purchaseOrder->lines as $poLine) {
            if (!empty($poLine->source_plan_line_id)) {
                $planLineId = $poLine->source_plan_line_id;
                $quantity = (float) $poLine->quantity;

                if (!isset($planLineUpdates[$planLineId])) {
                    $planLineUpdates[$planLineId] = 0;
                }
                $planLineUpdates[$planLineId] += $quantity;
            }
        }

        if (!empty($planLineUpdates)) {
            $purchasePlanService = app(PurchasePlanService::class);
            $purchasePlanService->updateOrderedQuantities($planLineUpdates);
        }
    }
}


