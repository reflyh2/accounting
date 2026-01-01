<?php

namespace App\Services\Purchasing;

use App\Enums\Documents\PurchasePlanStatus;
use App\Exceptions\PurchaseOrderException;
use App\Models\Branch;
use App\Models\PurchasePlan;
use App\Models\PurchasePlanLine;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchasePlanService
{
    public function create(array $payload, ?Authenticatable $actor = null): PurchasePlan
    {
        $actor ??= Auth::user();

        return DB::transaction(function () use ($payload, $actor) {
            $branch = Branch::with('branchGroup')->findOrFail($payload['branch_id']);
            $companyId = $branch->branchGroup?->company_id;

            if (!$companyId) {
                throw new PurchaseOrderException('Cabang tidak terhubung ke perusahaan manapun.');
            }

            $planDate = Carbon::parse($payload['plan_date']);

            $purchasePlan = PurchasePlan::create([
                'company_id' => $companyId,
                'branch_id' => $branch->id,
                'plan_number' => $this->generatePlanNumber($companyId, $branch->id, $planDate),
                'plan_date' => $planDate,
                'required_date' => isset($payload['required_date']) ? Carbon::parse($payload['required_date']) : null,
                'source_type' => $payload['source_type'] ?? 'manual',
                'notes' => $payload['notes'] ?? null,
                'created_by' => $actor?->getAuthIdentifier(),
            ]);

            $this->persistLines($purchasePlan, $payload['lines'] ?? []);

            return $purchasePlan->fresh([
                'branch.branchGroup.company',
                'lines.product',
                'lines.variant',
                'lines.uom',
            ]);
        });
    }

    public function update(PurchasePlan $purchasePlan, array $payload, ?Authenticatable $actor = null): PurchasePlan
    {
        $this->assertDraft($purchasePlan);

        $actor ??= Auth::user();

        return DB::transaction(function () use ($purchasePlan, $payload, $actor) {
            $purchasePlan->update([
                'plan_date' => Carbon::parse($payload['plan_date']),
                'required_date' => isset($payload['required_date']) ? Carbon::parse($payload['required_date']) : null,
                'source_type' => $payload['source_type'] ?? $purchasePlan->source_type,
                'notes' => $payload['notes'] ?? null,
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            $purchasePlan->lines()->delete();
            $this->persistLines($purchasePlan, $payload['lines'] ?? []);

            return $purchasePlan->fresh([
                'branch.branchGroup.company',
                'lines.product',
                'lines.variant',
                'lines.uom',
            ]);
        });
    }

    public function delete(PurchasePlan $purchasePlan): void
    {
        $this->assertDraft($purchasePlan);

        DB::transaction(function () use ($purchasePlan): void {
            $purchasePlan->lines()->delete();
            $purchasePlan->delete();
        });
    }

    public function confirm(PurchasePlan $purchasePlan, ?Authenticatable $actor = null): PurchasePlan
    {
        $this->assertDraft($purchasePlan);

        $actor ??= Auth::user();

        return DB::transaction(function () use ($purchasePlan, $actor) {
            $purchasePlan->transitionTo(PurchasePlanStatus::CONFIRMED, $actor);

            $purchasePlan->update([
                'confirmed_by' => $actor?->getAuthIdentifier(),
                'confirmed_at' => now(),
            ]);

            return $purchasePlan->fresh();
        });
    }

    public function close(PurchasePlan $purchasePlan, ?Authenticatable $actor = null): PurchasePlan
    {
        if ($purchasePlan->status !== PurchasePlanStatus::CONFIRMED->value) {
            throw new PurchaseOrderException('Rencana pembelian harus dikonfirmasi terlebih dahulu.');
        }

        $actor ??= Auth::user();

        return DB::transaction(function () use ($purchasePlan, $actor) {
            $purchasePlan->transitionTo(PurchasePlanStatus::CLOSED, $actor);

            $purchasePlan->update([
                'closed_by' => $actor?->getAuthIdentifier(),
                'closed_at' => now(),
            ]);

            return $purchasePlan->fresh();
        });
    }

    public function cancel(PurchasePlan $purchasePlan, ?Authenticatable $actor = null, ?string $reason = null): PurchasePlan
    {
        if (!in_array($purchasePlan->status, [PurchasePlanStatus::DRAFT->value, PurchasePlanStatus::CONFIRMED->value], true)) {
            throw new PurchaseOrderException('Rencana pembelian tidak dapat dibatalkan.');
        }

        $actor ??= Auth::user();

        return DB::transaction(function () use ($purchasePlan, $actor, $reason) {
            $purchasePlan->transitionTo(PurchasePlanStatus::CANCELLED, $actor);

            $purchasePlan->update([
                'cancelled_by' => $actor?->getAuthIdentifier(),
                'cancelled_at' => now(),
                'notes' => $reason ? ($purchasePlan->notes ? $purchasePlan->notes . "\n\nAlasan pembatalan: " . $reason : "Alasan pembatalan: " . $reason) : $purchasePlan->notes,
            ]);

            return $purchasePlan->fresh();
        });
    }

    /**
     * Get allowed status transitions for a purchase plan.
     *
     * @param PurchasePlan $purchasePlan
     * @return array
     */
    public function allowedStatuses(PurchasePlan $purchasePlan): array
    {
        $transitions = [];

        if ($purchasePlan->status === PurchasePlanStatus::DRAFT->value) {
            $transitions[] = [
                'status' => PurchasePlanStatus::CONFIRMED->value,
                'label' => 'Konfirmasi',
                'action' => 'confirm',
            ];
            $transitions[] = [
                'status' => PurchasePlanStatus::CANCELLED->value,
                'label' => 'Batalkan',
                'action' => 'cancel',
            ];
        }

        if ($purchasePlan->status === PurchasePlanStatus::CONFIRMED->value) {
            $transitions[] = [
                'status' => PurchasePlanStatus::CLOSED->value,
                'label' => 'Tutup',
                'action' => 'close',
            ];
            $transitions[] = [
                'status' => PurchasePlanStatus::CANCELLED->value,
                'label' => 'Batalkan',
                'action' => 'cancel',
            ];
        }

        return $transitions;
    }

    /**
     * Get confirmed purchase plan lines that are available for ordering.
     *
     * @param int $branchId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAvailableLinesForOrdering(int $branchId)
    {
        return PurchasePlanLine::query()
            ->whereHas('purchasePlan', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId)
                    ->where('status', PurchasePlanStatus::CONFIRMED->value);
            })
            ->whereRaw('planned_qty > ordered_qty')
            ->with(['purchasePlan', 'product', 'variant', 'uom'])
            ->get();
    }

    /**
     * Update ordered quantity on plan lines when a PO is created.
     *
     * @param array $planLineUpdates Array of ['plan_line_id' => qty_ordered]
     */
    public function updateOrderedQuantities(array $planLineUpdates): void
    {
        foreach ($planLineUpdates as $planLineId => $qtyOrdered) {
            $planLine = PurchasePlanLine::lockForUpdate()->find($planLineId);
            if ($planLine) {
                $planLine->ordered_qty = $this->roundQuantity((float) $planLine->ordered_qty + (float) $qtyOrdered);
                $planLine->save();
            }
        }
    }

    private function persistLines(PurchasePlan $purchasePlan, array $lines): void
    {
        $lineNumber = 1;

        foreach ($lines as $line) {
            if (empty($line['product_id']) || empty($line['planned_qty'])) {
                continue;
            }

            $purchasePlan->lines()->create([
                'product_id' => $line['product_id'],
                'product_variant_id' => $line['product_variant_id'] ?? null,
                'uom_id' => $line['uom_id'],
                'line_number' => $lineNumber++,
                'description' => $line['description'] ?? null,
                'planned_qty' => $this->roundQuantity((float) $line['planned_qty']),
                'required_date' => isset($line['required_date']) ? Carbon::parse($line['required_date']) : null,
                'source_type' => $line['source_type'] ?? 'manual',
                'source_ref_id' => $line['source_ref_id'] ?? null,
                'notes' => $line['notes'] ?? null,
            ]);
        }
    }

    private function generatePlanNumber(int $companyId, int $branchId, Carbon $planDate): string
    {
        $prefix = 'PPLAN';

        $latest = PurchasePlan::withTrashed()
            ->where('branch_id', $branchId)
            ->whereYear('plan_date', $planDate->year)
            ->orderByDesc('plan_number')
            ->value('plan_number');

        $nextSequence = 1;

        if ($latest) {
            $segments = explode('.', $latest);
            $last = (int) (end($segments) ?: 0);
            $nextSequence = $last + 1;
        }

        $companySegment = str_pad((string) $companyId, 2, '0', STR_PAD_LEFT);
        $branchSegment = str_pad((string) $branchId, 3, '0', STR_PAD_LEFT);
        $yearSegment = $planDate->format('y');
        $sequence = str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);

        return sprintf('%s.%s%s.%s.%s', $prefix, $companySegment, $branchSegment, $yearSegment, $sequence);
    }

    private function assertDraft(PurchasePlan $purchasePlan): void
    {
        if ($purchasePlan->status !== PurchasePlanStatus::DRAFT->value) {
            throw new PurchaseOrderException('Rencana pembelian ini sudah dikonfirmasi dan tidak dapat diubah.');
        }
    }

    private function roundQuantity(float $value): float
    {
        return round($value, 3);
    }
}
