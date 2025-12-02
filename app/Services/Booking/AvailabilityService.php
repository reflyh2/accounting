<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Exceptions\BookingException;
use App\Models\AvailabilityRule;
use App\Models\BookingLine;
use App\Models\BookingLineResource;
use App\Models\Occurrence;
use App\Models\ResourceInstance;
use App\Models\ResourcePool;
use App\Services\Booking\DTO\AvailabilityResult;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AvailabilityService
{
    public function searchPoolAvailability(int $poolId, CarbonInterface $start, CarbonInterface $end, int $qty): AvailabilityResult
    {
        $pool = ResourcePool::with('activeInstances')->findOrFail($poolId);
        $this->guardDateRange($start, $end);

        $blockingRules = $this->blockingRules($poolId, $start, $end);
        $conflicts = $this->overlappingBookingLinesQuery($poolId, $start, $end)->get();
        $bookedQty = (int) $conflicts->sum('qty');
        $capacity = $this->determineCapacity($pool, $start, $end);
        $availableQty = max($capacity - $bookedQty, 0);

        return new AvailabilityResult(
            $pool,
            $start->clone(),
            $end->clone(),
            $qty,
            $capacity,
            $bookedQty,
            $availableQty,
            $blockingRules,
            $conflicts
        );
    }

    public function findFreeInstances(int $poolId, CarbonInterface $start, CarbonInterface $end, int $qty): Collection
    {
        $this->guardDateRange($start, $end);

        $busyInstanceIds = $this->busyInstanceIds($poolId, $start, $end);

        $query = ResourceInstance::query()
            ->where('resource_pool_id', $poolId)
            ->where('status', 'active')
            ->whereNotIn('id', $busyInstanceIds)
            ->orderBy('code');

        if ($qty > 0) {
            $query->limit($qty);
        }

        return $query->get();
    }

    private function guardDateRange(CarbonInterface $start, CarbonInterface $end): void
    {
        if ($end->lessThanOrEqualTo($start)) {
            throw new BookingException('End date must be after start date.');
        }
    }

    private function blockingRules(int $poolId, CarbonInterface $start, CarbonInterface $end): array
    {
        return AvailabilityRule::query()
            ->where('resource_pool_id', $poolId)
            ->whereIn('rule_type', ['close', 'blackout'])
            ->where('start_datetime', '<', $end)
            ->where('end_datetime', '>', $start)
            ->orderBy('start_datetime')
            ->get()
            ->map(fn ($rule) => [
                'type' => $rule->rule_type,
                'start' => $rule->start_datetime?->toIso8601String(),
                'end' => $rule->end_datetime?->toIso8601String(),
                'notes' => $rule->notes,
            ])
            ->toArray();
    }

    private function overlappingBookingLinesQuery(int $poolId, CarbonInterface $start, CarbonInterface $end): Builder
    {
        $now = now();

        return BookingLine::query()
            ->with(['booking.partner', 'assignedInstance'])
            ->where('resource_pool_id', $poolId)
            ->where('start_datetime', '<', $end)
            ->where('end_datetime', '>', $start)
            ->whereHas('booking', fn (Builder $booking) => $this->applyBlockingStatusConstraint($booking, $now));
    }

    private function determineCapacity(ResourcePool $pool, CarbonInterface $start, CarbonInterface $end): int
    {
        $occurrenceCapacity = Occurrence::query()
            ->where('resource_pool_id', $pool->id)
            ->where('status', '!=', 'closed')
            ->where('start_datetime', '<', $end)
            ->where('end_datetime', '>', $start)
            ->orderBy('capacity')
            ->value('capacity');

        if ($occurrenceCapacity !== null) {
            return (int) $occurrenceCapacity;
        }

        if ($pool->default_capacity !== null) {
            return (int) $pool->default_capacity;
        }

        $activeInstances = $pool->activeInstances()->count();
        if ($activeInstances > 0) {
            return $activeInstances;
        }

        return $pool->instances()->count();
    }

    private function busyInstanceIds(int $poolId, CarbonInterface $start, CarbonInterface $end): Collection
    {
        $now = now();

        $direct = BookingLine::query()
            ->where('resource_pool_id', $poolId)
            ->where('start_datetime', '<', $end)
            ->where('end_datetime', '>', $start)
            ->whereNotNull('resource_instance_id')
            ->whereHas('booking', fn (Builder $booking) => $this->applyBlockingStatusConstraint($booking, $now))
            ->pluck('resource_instance_id');

        $fromPivot = BookingLineResource::query()
            ->whereHas('bookingLine', function (Builder $line) use ($poolId, $start, $end, $now) {
                $line->where('resource_pool_id', $poolId)
                    ->where('start_datetime', '<', $end)
                    ->where('end_datetime', '>', $start)
                    ->whereHas('booking', fn (Builder $booking) => $this->applyBlockingStatusConstraint($booking, $now));
            })
            ->pluck('resource_instance_id');

        return $direct->merge($fromPivot)->filter()->unique()->values();
    }

    private function applyBlockingStatusConstraint(Builder $query, CarbonInterface $now): Builder
    {
        return $query->where(function (Builder $statusQuery) use ($now) {
            $statusQuery->whereIn('status', BookingStatus::blockingStatuses())
                ->orWhere(function (Builder $hold) use ($now) {
                    $hold->where('status', BookingStatus::HOLD->value)
                        ->where(function (Builder $heldUntil) use ($now) {
                            $heldUntil->whereNull('held_until')
                                ->orWhere('held_until', '>', $now);
                        });
                });
        });
    }
}

