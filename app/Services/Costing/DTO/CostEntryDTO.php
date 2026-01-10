<?php

namespace App\Services\Costing\DTO;

use App\Enums\CostEntrySource;
use App\Enums\CostObjectType;
use Carbon\CarbonInterface;

/**
 * DTO for creating a cost entry.
 */
readonly class CostEntryDTO
{
    public function __construct(
        public int $companyId,
        public CostEntrySource $sourceType,
        public int $sourceId,
        public float $amount,
        public int $currencyId,
        public float $exchangeRate,
        public CarbonInterface $costDate,
        public ?int $productId = null,
        public ?int $productVariantId = null,
        public ?int $costPoolId = null,
        public ?CostObjectType $costObjectType = null,
        public ?int $costObjectId = null,
        public ?string $description = null,
        public ?string $notes = null,
    ) {
    }

    /**
     * Get the amount in base currency.
     */
    public function getAmountBase(): float
    {
        return $this->amount * $this->exchangeRate;
    }
}
