<?php

namespace App\Services\Catalog;

use App\Models\PartnerGroupMember;
use App\Models\PriceList;
use App\Models\PriceListTarget;
use Illuminate\Support\Carbon;

class PriceListResolver
{
    public function resolve(array $ctx = []): ?PriceList
    {
        $date = $this->resolveDate($ctx);
        $channel = $ctx['channel'] ?? null;
        $companyId = $ctx['company_id'] ?? null;
        $partnerId = $ctx['partner_id'] ?? null;
        $currencyId = $ctx['currency_id'] ?? null;

        $groupIds = $this->resolvePartnerGroupIds($ctx, $date);

        $levels = $this->buildPrecedenceLevels($partnerId, $groupIds, $companyId);

        foreach ($levels as $level) {
            $target = $this->matchTarget($level, $channel, $currencyId, $date);
            if ($target) {
                return $target->priceList;
            }
        }

        return $this->fallbackPriceList($companyId, $currencyId, $date);
    }

    private function resolveDate(array $ctx): Carbon
    {
        if (!empty($ctx['date'])) {
            return Carbon::parse($ctx['date']);
        }

        return now();
    }

    /**
     * @return int[]
     */
    private function resolvePartnerGroupIds(array $ctx, Carbon $date): array
    {
        $ids = collect();

        if (!empty($ctx['partner_group_id'])) {
            $ids->push((int) $ctx['partner_group_id']);
        }

        if (!empty($ctx['partner_group_ids']) && is_array($ctx['partner_group_ids'])) {
            $ids = $ids->merge(array_map('intval', $ctx['partner_group_ids']));
        }

        $partnerId = $ctx['partner_id'] ?? null;
        if ($partnerId) {
            $membershipQuery = PartnerGroupMember::query()
                ->active($date)
                ->where('partner_id', $partnerId);

            if (!empty($ctx['company_id'])) {
                $membershipQuery->where('company_id', $ctx['company_id']);
            }

            $ids = $ids->merge($membershipQuery->pluck('partner_group_id'));
        }

        return $ids->unique()->values()->all();
    }

    private function buildPrecedenceLevels(?int $partnerId, array $groupIds, ?int $companyId): array
    {
        $levels = [];

        if ($partnerId && $companyId) {
            $levels[] = ['partner_id' => $partnerId, 'company_id' => $companyId];
        }

        if ($partnerId) {
            $levels[] = ['partner_id' => $partnerId];
        }

        foreach ($groupIds as $groupId) {
            if ($companyId) {
                $levels[] = ['partner_group_id' => $groupId, 'company_id' => $companyId];
            }
            $levels[] = ['partner_group_id' => $groupId];
        }

        if ($companyId) {
            $levels[] = ['company_id' => $companyId];
        }

        $levels[] = []; // global default

        return $levels;
    }

    private function matchTarget(array $criteria, ?string $channel, ?int $currencyId, Carbon $date): ?PriceListTarget
    {
        $query = PriceListTarget::query()
            ->with('priceList')
            ->where('is_active', true)
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', $date);
            })
            ->whereHas('priceList', function ($q) use ($date, $currencyId) {
                $q->where('is_active', true)
                    ->where(function ($query) use ($date) {
                        $query->whereNull('valid_from')
                            ->orWhere('valid_from', '<=', $date);
                    })
                    ->where(function ($query) use ($date) {
                        $query->whereNull('valid_to')
                            ->orWhere('valid_to', '>=', $date);
                    });

                // Filter by currency if provided
                if ($currencyId) {
                    $q->where('currency_id', $currencyId);
                }
            });

        $this->applyDimensionFilter($query, 'partner_id', $criteria);
        $this->applyDimensionFilter($query, 'partner_group_id', $criteria);
        $this->applyDimensionFilter($query, 'company_id', $criteria);

        $query->where(function ($q) use ($channel) {
            if ($channel) {
                $q->where('channel', $channel)
                    ->orWhereNull('channel');
            } else {
                $q->whereNull('channel');
            }
        });

        $query->orderBy('priority')
            ->orderByDesc('valid_from')
            ->orderByDesc('id');

        return $query->first();
    }

    private function applyDimensionFilter($query, string $column, array $criteria): void
    {
        if (array_key_exists($column, $criteria)) {
            $query->where($column, $criteria[$column]);
        } else {
            $query->whereNull($column);
        }
    }

    private function fallbackPriceList(?int $companyId, ?int $currencyId, Carbon $date): ?PriceList
    {
        $query = PriceList::query()
            ->where('is_active', true)
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', $date);
            })
            ->orderBy('id');

        // Apply currency filter if provided
        if ($currencyId) {
            $query->where('currency_id', $currencyId);
        }

        if ($companyId) {
            $companyScoped = (clone $query)->where('company_id', $companyId)->first();
            if ($companyScoped) {
                return $companyScoped;
            }
        }

        return $query->first();
    }
}
