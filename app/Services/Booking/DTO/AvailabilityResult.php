<?php

namespace App\Services\Booking\DTO;

use App\Models\ResourcePool;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

readonly class AvailabilityResult
{
    public function __construct(
        public ResourcePool $pool,
        public Carbon $start,
        public Carbon $end,
        public int $requestedQty,
        public int $capacity,
        public int $bookedQty,
        public int $availableQty,
        public array $blockingRules = [],
        public ?Collection $conflicts = null,
    ) {
        $this->conflicts ??= collect();
    }

    public function isBlocked(): bool
    {
        return !empty($this->blockingRules);
    }

    public function toArray(): array
    {
        return [
            'pool_id' => $this->pool->id,
            'pool_name' => $this->pool->name,
            'start' => $this->start->toIso8601String(),
            'end' => $this->end->toIso8601String(),
            'requested_qty' => $this->requestedQty,
            'capacity' => $this->capacity,
            'booked_qty' => $this->bookedQty,
            'available_qty' => $this->availableQty,
            'blocking_rules' => $this->blockingRules,
        ];
    }
}

