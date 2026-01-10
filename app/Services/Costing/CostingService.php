<?php

namespace App\Services\Costing;

use App\Domain\Accounting\DTO\AccountingEntry;
use App\Domain\Accounting\DTO\AccountingEventPayload;
use App\Enums\AccountingEventCode;
use App\Enums\CostModel;
use App\Events\AccountingEventDispatched;
use App\Models\CostAllocation;
use App\Models\CostEntry;
use App\Models\CostPool;
use App\Models\InventoryCostConsumption;
use App\Models\InvoiceDetailCost;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Services\Costing\DTO\AttachCostDTO;
use App\Services\Costing\DTO\CostEntryDTO;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CostingService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Cost Entry Management
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Record a new cost entry from a source document.
     */
    public function recordCostEntry(CostEntryDTO $dto, ?Authenticatable $actor = null): CostEntry
    {
        $actor ??= Auth::user();

        $entry = CostEntry::create([
            'company_id' => $dto->companyId,
            'source_type' => $dto->sourceType->value,
            'source_id' => $dto->sourceId,
            'product_id' => $dto->productId,
            'product_variant_id' => $dto->productVariantId,
            'cost_pool_id' => $dto->costPoolId,
            'cost_object_type' => $dto->costObjectType?->value,
            'cost_object_id' => $dto->costObjectId,
            'description' => $dto->description,
            'amount' => $dto->amount,
            'currency_id' => $dto->currencyId,
            'exchange_rate' => $dto->exchangeRate,
            'amount_base' => $dto->getAmountBase(),
            'amount_allocated' => 0,
            'is_fully_allocated' => false,
            'cost_date' => $dto->costDate,
            'notes' => $dto->notes,
            'created_by' => $actor?->global_id,
        ]);

        // If linked to a cost pool, update the pool accumulation
        if ($dto->costPoolId) {
            $pool = CostPool::find($dto->costPoolId);
            $pool?->recordAccumulation($dto->getAmountBase());
        }

        return $entry;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cost Attribution
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Attach a cost to a sales invoice line.
     */
    public function attachCostToLine(AttachCostDTO $dto): InvoiceDetailCost
    {
        $detailCost = InvoiceDetailCost::create([
            'sales_invoice_line_id' => $dto->salesInvoiceLineId,
            'cost_entry_id' => $dto->costEntryId,
            'inventory_cost_consumption_id' => $dto->inventoryCostConsumptionId,
            'cost_allocation_id' => $dto->costAllocationId,
            'amount' => $dto->amount,
            'amount_base' => $dto->amountBase,
            'cost_source' => $dto->costSource,
        ]);

        // If from a cost entry, update allocation tracking
        if ($dto->costEntryId) {
            $entry = CostEntry::find($dto->costEntryId);
            $entry?->recordAllocation($dto->amountBase);
        }

        return $detailCost;
    }

    /**
     * Attach inventory layer costs to a sales invoice line.
     * Called when posting invoice for inventory_layer cost model products.
     */
    public function attachInventoryCosts(
        SalesInvoiceLine $line,
        array $consumptionIds
    ): void {
        foreach ($consumptionIds as $consumptionId) {
            $consumption = InventoryCostConsumption::find($consumptionId);
            if (!$consumption) {
                continue;
            }

            $amount = (float) $consumption->qty_consumed * (float) $consumption->unit_cost;

            $this->attachCostToLine(AttachCostDTO::fromInventoryConsumption(
                salesInvoiceLineId: $line->id,
                inventoryCostConsumptionId: $consumptionId,
                amount: $amount,
                amountBase: $amount, // Inventory is always in base currency
            ));
        }

        $this->updateLineCostTotals($line);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cost Retrieval
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get total cost for a sales invoice line.
     */
    public function getCostForLine(SalesInvoiceLine $line): float
    {
        return (float) $line->costs()->sum('amount_base');
    }

    /**
     * Get total cost for a sales invoice.
     */
    public function getCostForInvoice(SalesInvoice $invoice): float
    {
        return (float) $invoice->lines()
            ->with('costs')
            ->get()
            ->sum(fn ($line) => $this->getCostForLine($line));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Gross Margin Calculation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calculate gross margin for an invoice line.
     */
    public function getGrossMarginForLine(SalesInvoiceLine $line): array
    {
        $revenue = (float) $line->line_total_base;
        $cost = $this->getCostForLine($line);
        $margin = $revenue - $cost;
        $marginPercent = $revenue > 0 ? ($margin / $revenue) * 100 : null;

        return [
            'revenue' => $revenue,
            'cost' => $cost,
            'margin' => $margin,
            'margin_percent' => $marginPercent,
        ];
    }

    /**
     * Calculate gross margin for a sales invoice.
     */
    public function getGrossMarginForInvoice(SalesInvoice $invoice): array
    {
        $revenue = (float) $invoice->lines()->sum('line_total_base');
        $cost = $this->getCostForInvoice($invoice);
        $margin = $revenue - $cost;
        $marginPercent = $revenue > 0 ? ($margin / $revenue) * 100 : null;

        return [
            'revenue' => $revenue,
            'cost' => $cost,
            'margin' => $margin,
            'margin_percent' => $marginPercent,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cost Pool Allocation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Allocate costs from a pool to invoice lines.
     *
     * @param CostPool $pool
     * @param array $allocations Array of ['sales_invoice_line_id' => int, 'numerator' => float]
     * @param float $denominator Total denominator for allocation ratio
     * @param string $periodStart Period start date
     * @param string $periodEnd Period end date
     */
    public function allocateFromPool(
        CostPool $pool,
        array $allocations,
        float $denominator,
        string $periodStart,
        string $periodEnd,
        ?Authenticatable $actor = null
    ): array {
        $actor ??= Auth::user();
        $amountToAllocate = $pool->unallocated_amount;
        $createdAllocations = [];

        foreach ($allocations as $alloc) {
            $ratio = $denominator > 0 ? $alloc['numerator'] / $denominator : 0;
            $amount = $amountToAllocate * $ratio;

            $costAllocation = CostAllocation::create([
                'cost_pool_id' => $pool->id,
                'sales_invoice_line_id' => $alloc['sales_invoice_line_id'],
                'amount' => $amount,
                'amount_base' => $amount,
                'allocation_rule' => $pool->allocation_rule,
                'allocation_numerator' => $alloc['numerator'],
                'allocation_denominator' => $denominator,
                'allocation_ratio' => $ratio,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'created_by' => $actor?->global_id,
            ]);

            // Link to invoice detail cost
            $this->attachCostToLine(AttachCostDTO::fromAllocation(
                salesInvoiceLineId: $alloc['sales_invoice_line_id'],
                costAllocationId: $costAllocation->id,
                amount: $amount,
                amountBase: $amount,
            ));

            // Update line cost totals
            $line = SalesInvoiceLine::find($alloc['sales_invoice_line_id']);
            if ($line) {
                $this->updateLineCostTotals($line);
            }

            $createdAllocations[] = $costAllocation;
        }

        // Update pool allocated total
        $pool->recordAllocation(array_sum(array_column($createdAllocations, 'amount')));

        return $createdAllocations;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Invoice Line Cost Updates
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Update the cost totals on a sales invoice line.
     */
    public function updateLineCostTotals(SalesInvoiceLine $line): void
    {
        $costTotal = $this->getCostForLine($line);
        $quantity = (float) $line->quantity_base ?: (float) $line->quantity;
        $unitCost = $quantity > 0 ? $costTotal / $quantity : 0;
        $grossMargin = (float) $line->line_total_base - $costTotal;

        $line->update([
            'unit_cost' => round($unitCost, 4),
            'cost_total' => round($costTotal, 4),
            'gross_margin' => round($grossMargin, 4),
        ]);
    }

    /**
     * Update cost totals for all lines in an invoice.
     */
    public function updateInvoiceCostTotals(SalesInvoice $invoice): void
    {
        foreach ($invoice->lines as $line) {
            $this->updateLineCostTotals($line);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cost Model Resolution
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get the cost model for a product.
     */
    public function getCostModelForProduct(Product $product): CostModel
    {
        return CostModel::tryFrom($product->cost_model) ?? CostModel::DIRECT_EXPENSE_PER_SALE;
    }

    /**
     * Check if a product uses inventory layer costing.
     */
    public function usesInventoryCosting(Product $product): bool
    {
        return $this->getCostModelForProduct($product)->usesInventoryLayers();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Accounting Integration
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Dispatch COGS recognition accounting event.
     */
    public function dispatchCogsRecognitionEvent(
        SalesInvoice $invoice,
        float $cogsAmount,
        ?Authenticatable $actor = null
    ): void {
        $actor ??= Auth::user();

        $payload = new AccountingEventPayload(
            code: AccountingEventCode::COGS_RECOGNIZED,
            companyId: $invoice->company_id,
            branchId: $invoice->branch_id,
            documentType: 'sales_invoice',
            documentId: $invoice->id,
            documentNumber: $invoice->invoice_number,
            currencyCode: $invoice->currency?->code ?? 'IDR',
            exchangeRate: (float) $invoice->exchange_rate,
            occurredAt: CarbonImmutable::now(),
            actorId: $actor?->global_id,
        );

        $payload->addLine(new AccountingEntry(
            role: 'cogs',
            direction: 'debit',
            amount: $cogsAmount,
        ));

        $payload->addLine(new AccountingEntry(
            role: 'inventory',
            direction: 'credit',
            amount: $cogsAmount,
        ));

        event(new AccountingEventDispatched($payload));
    }
}
