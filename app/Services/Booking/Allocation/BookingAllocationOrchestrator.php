<?php

namespace App\Services\Booking\Allocation;

use App\Domain\Accounting\DTO\AccountingEntry;
use App\Domain\Accounting\DTO\AccountingEventPayload;
use App\Enums\AccountingEventCode;
use App\Events\AccountingEventDispatched;
use App\Exceptions\BookingAllocationException;
use App\Models\Booking;
use App\Models\BookingAllocationRun;
use App\Models\CostAllocation;
use App\Models\CostPool;
use App\Models\SalesInvoiceLine;
use App\Services\Costing\CostingService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BookingAllocationOrchestrator
{
    public function __construct(
        private CostingService $costingService,
        private NumeratorResolver $numeratorResolver,
    ) {}

    /**
     * Run a booking-driven allocation for a single (company, cost_pool, period).
     *
     * Allocates the pool's unallocated balance pro-rata across SalesInvoiceLines
     * that are tied to self_operated booking lines whose service period overlaps
     * the run period and whose backing booking references the pool's asset.
     */
    public function run(
        int $companyId,
        int $costPoolId,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        ?Authenticatable $actor = null
    ): BookingAllocationRun {
        $actor ??= Auth::user();

        if ($periodEnd->lessThanOrEqualTo($periodStart)) {
            throw new BookingAllocationException('Periode akhir harus lebih besar dari periode awal.');
        }

        return DB::transaction(function () use ($companyId, $costPoolId, $periodStart, $periodEnd, $actor) {
            /** @var CostPool|null $pool */
            $pool = CostPool::query()
                ->where('id', $costPoolId)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first();

            if (! $pool) {
                throw new BookingAllocationException('Cost pool tidak ditemukan.');
            }

            $existing = BookingAllocationRun::query()
                ->where('company_id', $companyId)
                ->where('cost_pool_id', $costPoolId)
                ->where('period_start', $periodStart->toDateString())
                ->where('period_end', $periodEnd->toDateString())
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->status === 'posted') {
                throw new BookingAllocationException(
                    'Allocation run untuk periode ini sudah dipost. Reverse terlebih dahulu jika ingin memproses ulang.'
                );
            }

            $linesWithNumerators = $this->gatherEligibleLines($pool, $periodStart, $periodEnd);

            $denominator = 0.0;
            foreach ($linesWithNumerators as $row) {
                $denominator += $row['numerator'];
            }

            $basis = $linesWithNumerators[0]['basis'] ?? 'revenue';
            $poolAmount = (float) $pool->unallocated_amount;

            $run = $existing ?? BookingAllocationRun::create([
                'company_id' => $companyId,
                'branch_id' => $pool->branch_id,
                'asset_id' => $pool->asset_id,
                'cost_pool_id' => $costPoolId,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'allocation_basis' => $basis,
                'denominator' => 0,
                'pool_amount' => 0,
                'status' => 'draft',
                'created_by' => $actor?->getAuthIdentifier(),
            ]);

            $run->update([
                'allocation_basis' => $basis,
                'denominator' => $denominator,
                'pool_amount' => $poolAmount,
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            if ($denominator <= 0 || $poolAmount <= 0) {
                $run->update([
                    'status' => 'posted',
                    'posted_at' => now(),
                    'posted_by' => $actor?->getAuthIdentifier(),
                    'notes' => $denominator <= 0
                        ? 'Tidak ada baris booking yang memenuhi syarat untuk periode ini.'
                        : 'Saldo pool tidak tersedia untuk dialokasikan.',
                ]);

                return $run->fresh();
            }

            $allocationsPayload = array_map(
                fn ($row) => [
                    'sales_invoice_line_id' => $row['line']->id,
                    'numerator' => $row['numerator'],
                ],
                $linesWithNumerators
            );

            $created = $this->costingService->allocateFromPool(
                $pool,
                $allocationsPayload,
                $denominator,
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
                $actor
            );

            $allocationIds = array_filter(array_map(fn ($a) => $a->id ?? null, $created));
            if (! empty($allocationIds)) {
                CostAllocation::query()
                    ->whereIn('id', $allocationIds)
                    ->update(['booking_allocation_run_id' => $run->id]);
            }

            $totalCogs = 0.0;
            foreach ($created as $allocation) {
                $totalCogs += (float) $allocation->amount;
            }

            $this->dispatchPoolCogsEvent($run, $totalCogs, $actor);

            $run->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => $actor?->getAuthIdentifier(),
            ]);

            return $run->fresh();
        });
    }

    /**
     * Reverse a posted allocation run: detach cost allocations, restore the pool,
     * recompute SI line cost totals, and dispatch the reversal event.
     */
    public function reverse(BookingAllocationRun $run, ?Authenticatable $actor = null): BookingAllocationRun
    {
        $actor ??= Auth::user();

        if ($run->status !== 'posted') {
            throw new BookingAllocationException('Hanya allocation run yang sudah dipost yang dapat direverse.');
        }

        return DB::transaction(function () use ($run, $actor) {
            $run->loadMissing('allocations.salesInvoiceLine');

            $totalReversed = 0.0;
            foreach ($run->allocations as $allocation) {
                $totalReversed += (float) $allocation->amount;

                $line = $allocation->salesInvoiceLine;
                if ($line) {
                    $line->cost_total = max(0, (float) $line->cost_total - (float) $allocation->amount);
                    $line->gross_margin = (float) $line->line_total_base - (float) $line->cost_total;
                    $quantity = (float) $line->quantity_base ?: (float) $line->quantity;
                    $line->unit_cost = $quantity > 0 ? $line->cost_total / $quantity : 0;
                    $line->save();
                }

                \App\Models\InvoiceDetailCost::query()
                    ->where('cost_allocation_id', $allocation->id)
                    ->delete();
            }

            CostAllocation::query()
                ->where('booking_allocation_run_id', $run->id)
                ->delete();

            /** @var CostPool|null $pool */
            $pool = CostPool::query()->where('id', $run->cost_pool_id)->lockForUpdate()->first();
            if ($pool) {
                $pool->total_allocated = max(0, (float) $pool->total_allocated - $totalReversed);
                $pool->save();
            }

            $run->update([
                'status' => 'reversed',
                'reversed_at' => now(),
                'reversed_by' => $actor?->getAuthIdentifier(),
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            $this->dispatchPoolCogsReversedEvent($run, $totalReversed, $actor);

            return $run->fresh();
        });
    }

    /**
     * @return array<int, array{line: SalesInvoiceLine, numerator: float, basis: string}>
     */
    private function gatherEligibleLines(CostPool $pool, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        $assetId = $pool->asset_id;

        // Find bookings in this company whose self_operated lines overlap the period
        // and (when the pool is asset-bound) whose resource_pool's instance points at that asset.
        $query = SalesInvoiceLine::query()
            ->whereHas('salesOrderLine.bookingLine.booking', function ($q) use ($pool) {
                $q->where('company_id', $pool->company_id)
                    ->where('fulfillment_mode', 'self_operated');
            })
            ->whereHas('salesOrderLine.bookingLine', function ($q) use ($periodStart, $periodEnd) {
                $q->where('start_datetime', '<', $periodEnd->toDateTimeString())
                    ->where('end_datetime', '>', $periodStart->toDateTimeString());
            })
            ->whereHas('invoice', function ($q) {
                $q->where('status', 'posted');
            })
            ->whereDoesntHave('costs.costAllocation', function ($q) use ($pool) {
                // Avoid double-allocating the same line from the same pool
                $q->where('cost_pool_id', $pool->id);
            })
            ->with(['salesOrderLine.bookingLine.booking', 'salesOrderLine.bookingLine.pool']);

        $lines = $query->get();

        if ($assetId) {
            $lines = $lines->filter(function (SalesInvoiceLine $line) use ($assetId) {
                $bookingLine = $line->salesOrderLine?->bookingLine;
                $instanceAsset = $bookingLine?->assignedInstance?->asset_id;
                $poolInstancesAssetIds = $bookingLine?->pool?->instances?->pluck('asset_id')->filter()->all() ?? [];

                if ($instanceAsset) {
                    return $instanceAsset === $assetId;
                }

                return in_array($assetId, $poolInstancesAssetIds, true);
            })->values();
        }

        $rows = [];
        foreach ($lines as $line) {
            $resolved = $this->numeratorResolver->resolve($line);
            if ($resolved['numerator'] <= 0) {
                continue;
            }
            $rows[] = [
                'line' => $line,
                'numerator' => $resolved['numerator'],
                'basis' => $resolved['basis'],
            ];
        }

        return $rows;
    }

    private function dispatchPoolCogsEvent(BookingAllocationRun $run, float $amount, ?Authenticatable $actor): void
    {
        if ($amount <= 0) {
            return;
        }

        $payload = new AccountingEventPayload(
            AccountingEventCode::BOOKING_POOL_COGS_POSTED,
            $run->company_id,
            $run->branch_id,
            'booking_allocation_run',
            $run->id,
            'BAR-'.str_pad((string) $run->id, 6, '0', STR_PAD_LEFT),
            'IDR',
            1.0,
            CarbonImmutable::parse($run->period_end ?? now()),
            $actor?->getAuthIdentifier(),
        );

        $payload->setLines([
            AccountingEntry::debit('cogs_booking', $amount),
            AccountingEntry::credit('cost_pool_clearing', $amount),
        ]);

        event(new AccountingEventDispatched($payload));
    }

    private function dispatchPoolCogsReversedEvent(BookingAllocationRun $run, float $amount, ?Authenticatable $actor): void
    {
        if ($amount <= 0) {
            return;
        }

        $payload = new AccountingEventPayload(
            AccountingEventCode::BOOKING_POOL_COGS_REVERSED,
            $run->company_id,
            $run->branch_id,
            'booking_allocation_run',
            $run->id,
            'BAR-'.str_pad((string) $run->id, 6, '0', STR_PAD_LEFT).'-REV',
            'IDR',
            1.0,
            CarbonImmutable::now(),
            $actor?->getAuthIdentifier(),
        );

        $payload->setLines([
            AccountingEntry::debit('cost_pool_clearing', $amount),
            AccountingEntry::credit('cogs_booking', $amount),
        ]);

        event(new AccountingEventDispatched($payload));
    }
}
