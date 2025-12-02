<?php

namespace App\Services\Inventory\DTO;

use Carbon\CarbonInterface;

readonly class AdjustDTO
{
    /**
     * @param AdjustLineDTO[] $lines
     */
    public function __construct(
        public CarbonInterface $transactionDate,
        public int $locationId,
        public array $lines,
        public ?string $reason = null,
        public ?string $notes = null,
        public ?string $valuationMethod = null,
    ) {
    }
}


