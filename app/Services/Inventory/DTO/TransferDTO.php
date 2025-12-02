<?php

namespace App\Services\Inventory\DTO;

use Carbon\CarbonInterface;

readonly class TransferDTO
{
    /**
     * @param TransferLineDTO[] $lines
     */
    public function __construct(
        public CarbonInterface $transactionDate,
        public int $locationIdFrom,
        public int $locationIdTo,
        public array $lines,
        public ?string $notes = null,
        public ?string $valuationMethod = null,
    ) {
    }
}


