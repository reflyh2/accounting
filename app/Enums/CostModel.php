<?php

namespace App\Enums;

/**
 * CostModel Enum
 *
 * Defines how costs are calculated for products.
 * Per COSTING.md: Each product declares a cost_model.
 */
enum CostModel: string
{
    case NONE = 'none';
    case INVENTORY_LAYER = 'inventory_layer';
    case DIRECT_EXPENSE_PER_SALE = 'direct_expense_per_sale';
    case JOB_COSTING = 'job_costing';
    case ASSET_USAGE_COSTING = 'asset_usage_costing';
    case PREPAID_CONSUMPTION = 'prepaid_consumption';
    case HYBRID = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'None',
            self::INVENTORY_LAYER => 'Inventory Layer (FIFO/Avg)',
            self::DIRECT_EXPENSE_PER_SALE => 'Direct Expense Per Sale',
            self::JOB_COSTING => 'Job/Project Costing',
            self::ASSET_USAGE_COSTING => 'Asset Usage Costing',
            self::PREPAID_CONSUMPTION => 'Prepaid Consumption',
            self::HYBRID => 'Hybrid (Mixed)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NONE => 'No COGS tracking (e.g., deposits, penalty fees)',
            self::INVENTORY_LAYER => 'COGS derived from inventory cost layers (FIFO/Moving Average)',
            self::DIRECT_EXPENSE_PER_SALE => 'Cost recorded directly per invoice line (tickets, resale)',
            self::JOB_COSTING => 'Costs accumulated on a job/project and allocated on invoicing',
            self::ASSET_USAGE_COSTING => 'Costs tied to asset usage (rental, depreciation)',
            self::PREPAID_CONSUMPTION => 'Costs consumed from prepaid balance (airline deposits)',
            self::HYBRID => 'Combination of direct and allocated costs',
        };
    }

    /**
     * Whether this cost model uses inventory layers.
     */
    public function usesInventoryLayers(): bool
    {
        return $this === self::INVENTORY_LAYER;
    }

    /**
     * Whether this cost model requires direct cost entry linking.
     */
    public function requiresDirectCostEntry(): bool
    {
        return in_array($this, [
            self::DIRECT_EXPENSE_PER_SALE,
            self::PREPAID_CONSUMPTION,
        ], true);
    }

    /**
     * Whether this cost model supports cost pool allocation.
     */
    public function supportsPoolAllocation(): bool
    {
        return in_array($this, [
            self::ASSET_USAGE_COSTING,
            self::JOB_COSTING,
            self::HYBRID,
        ], true);
    }
}
