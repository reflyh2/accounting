<?php

namespace App\Services\Costing\DTO;

/**
 * DTO for attaching a cost to an invoice line.
 */
readonly class AttachCostDTO
{
    public function __construct(
        public int $salesInvoiceLineId,
        public ?int $costEntryId = null,
        public ?int $inventoryCostConsumptionId = null,
        public ?int $costAllocationId = null,
        public float $amount = 0,
        public float $amountBase = 0,
        public string $costSource = 'direct', // inventory, direct, allocated
    ) {
    }

    /**
     * Create from a cost entry.
     */
    public static function fromCostEntry(
        int $salesInvoiceLineId,
        int $costEntryId,
        float $amount,
        float $amountBase
    ): self {
        return new self(
            salesInvoiceLineId: $salesInvoiceLineId,
            costEntryId: $costEntryId,
            amount: $amount,
            amountBase: $amountBase,
            costSource: 'direct',
        );
    }

    /**
     * Create from inventory cost consumption.
     */
    public static function fromInventoryConsumption(
        int $salesInvoiceLineId,
        int $inventoryCostConsumptionId,
        float $amount,
        float $amountBase
    ): self {
        return new self(
            salesInvoiceLineId: $salesInvoiceLineId,
            inventoryCostConsumptionId: $inventoryCostConsumptionId,
            amount: $amount,
            amountBase: $amountBase,
            costSource: 'inventory',
        );
    }

    /**
     * Create from cost pool allocation.
     */
    public static function fromAllocation(
        int $salesInvoiceLineId,
        int $costAllocationId,
        float $amount,
        float $amountBase
    ): self {
        return new self(
            salesInvoiceLineId: $salesInvoiceLineId,
            costAllocationId: $costAllocationId,
            amount: $amount,
            amountBase: $amountBase,
            costSource: 'allocated',
        );
    }
}
