<?php

namespace App\Services\Booking\DTO;

use Carbon\CarbonInterface;

readonly class BookingLineDTO
{
    public function __construct(
        public int $productId,
        public int $resourcePoolId,
        public CarbonInterface $start,
        public CarbonInterface $end,
        public int $qty,
        public float $unitPrice,
        public ?int $productVariantId = null,
        public ?float $taxAmount = null,
        public ?float $depositRequired = null,
        public ?int $occurrenceId = null,
        public ?int $resourceInstanceId = null,
    ) {
    }
}

