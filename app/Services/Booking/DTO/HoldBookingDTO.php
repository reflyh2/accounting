<?php

namespace App\Services\Booking\DTO;

use Carbon\CarbonInterface;

readonly class HoldBookingDTO
{
    /**
     * @param  BookingLineDTO[]  $lines
     */
    public function __construct(
        public int $partnerId,
        public int $currencyId,
        public string $bookingType,
        public ?CarbonInterface $heldUntil,
        public ?float $depositAmount,
        public ?string $sourceChannel,
        public ?string $notes,
        public array $lines,
        public ?CarbonInterface $bookedAt = null,
        public ?int $companyId = null,
        public ?int $branchId = null,
    ) {}
}
