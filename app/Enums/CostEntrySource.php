<?php

namespace App\Enums;

/**
 * CostEntrySource Enum
 *
 * Defines the source document type for cost entries.
 * Per COSTING.md: Cost entries are created from AP invoices, expense claims, payroll, journals.
 */
enum CostEntrySource: string
{
    case PURCHASE_INVOICE = 'purchase_invoice';
    case EXPENSE_CLAIM = 'expense_claim';
    case JOURNAL = 'journal';
    case PAYROLL = 'payroll';
    case INVENTORY_ISSUE = 'inventory_issue';
    case ASSET_DEPRECIATION = 'asset_depreciation';
    case SALES_INVOICE = 'sales_invoice';

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE_INVOICE => 'Purchase Invoice',
            self::EXPENSE_CLAIM => 'Expense Claim',
            self::JOURNAL => 'Journal Entry',
            self::PAYROLL => 'Payroll',
            self::INVENTORY_ISSUE => 'Inventory Issue',
            self::ASSET_DEPRECIATION => 'Asset Depreciation',
            self::SALES_INVOICE => 'Sales Invoice',
        };
    }

    /**
     * Get the model class for the source type.
     */
    public function modelClass(): ?string
    {
        return match ($this) {
            self::PURCHASE_INVOICE => \App\Models\PurchaseInvoice::class,
            self::JOURNAL => \App\Models\Journal::class,
            self::INVENTORY_ISSUE => \App\Models\InventoryTransaction::class,
            self::ASSET_DEPRECIATION => \App\Models\AssetDepreciationSchedule::class,
            self::SALES_INVOICE => \App\Models\SalesInvoice::class,
            default => null,
        };
    }
}


