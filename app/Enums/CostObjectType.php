<?php

namespace App\Enums;

/**
 * CostObjectType Enum
 *
 * Defines where costs can temporarily accumulate before allocation.
 * Per COSTING.md: Costs may be temporarily attached to a cost object.
 */
enum CostObjectType: string
{
    case INVOICE_DETAIL = 'invoice_detail';
    case BOOKING = 'booking';
    case WORK_ORDER = 'work_order';
    case JOB = 'job';
    case ASSET_INSTANCE = 'asset_instance';
    case COST_CENTER = 'cost_center';

    public function label(): string
    {
        return match ($this) {
            self::INVOICE_DETAIL => 'Invoice Detail',
            self::BOOKING => 'Booking',
            self::WORK_ORDER => 'Work Order',
            self::JOB => 'Job/Project',
            self::ASSET_INSTANCE => 'Asset Instance',
            self::COST_CENTER => 'Cost Center',
        };
    }

    /**
     * Get the model class for the object type.
     */
    public function modelClass(): ?string
    {
        return match ($this) {
            self::INVOICE_DETAIL => \App\Models\SalesInvoiceLine::class,
            self::BOOKING => \App\Models\Booking::class,
            self::WORK_ORDER => \App\Models\WorkOrder::class,
            self::ASSET_INSTANCE => \App\Models\ResourceInstance::class,
            default => null,
        };
    }
}
