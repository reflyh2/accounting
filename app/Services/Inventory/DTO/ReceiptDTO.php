<?php

namespace App\Services\Inventory\DTO;

use Carbon\CarbonInterface;

readonly class ReceiptDTO
{
    /**
     * @param ReceiptLineDTO[] $lines
     */
    public function __construct(
        public CarbonInterface $transactionDate,
        public int $locationId,
        public array $lines,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
        public ?string $notes = null,
        public ?string $valuationMethod = null,
    ) {
    }
}


