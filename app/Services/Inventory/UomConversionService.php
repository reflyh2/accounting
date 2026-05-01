<?php

namespace App\Services\Inventory;

use App\Models\Uom;
use App\Models\UomConversion;
use App\Models\UomConversionRule;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class UomConversionService
{
    private const SCALE = 6;

    /**
     * Convert quantity from one UOM to another.
     *
     * Lookup precedence:
     *   1. UomConversionRule matching the given $context (best-specificity wins,
     *      effective on the given date). Method may be fixed_ratio, avg_weight,
     *      or density. Allows cross-kind conversions (e.g. pcs → kg).
     *   2. Generic UomConversion (forward, then reverse). Same-kind only.
     *
     * @param  array  $context  Optional scope. Recognized keys:
     *                          - product_id (int|null)
     *                          - variant_id (int|null)
     *                          - company_id (int|null)
     *                          - partner_id (int|null)
     *                          - context (string|null) — 'purchase'|'sales'|'inventory'|'pricing'
     *                          - on_date (CarbonInterface|string|null) — defaults to today
     */
    public function convert(float $quantity, int $fromUomId, int $toUomId, array $context = []): float
    {
        if ($fromUomId === $toUomId) {
            return $this->round($quantity);
        }

        // 1. Try a scoped rule (forward)
        $rule = $this->findBestRule($fromUomId, $toUomId, $context);
        if ($rule) {
            return $this->applyRule($quantity, $rule, false);
        }
        // Reverse rule
        $reverseRule = $this->findBestRule($toUomId, $fromUomId, $context);
        if ($reverseRule) {
            return $this->applyRule($quantity, $reverseRule, true);
        }

        // 2. Fall back to global UomConversion (same-kind only)
        $from = Uom::findOrFail($fromUomId);
        $to = Uom::findOrFail($toUomId);
        if ($from->kind !== $to->kind) {
            throw new RuntimeException('Satuan tidak berada dalam jenis yang sama.');
        }

        $forward = UomConversion::query()
            ->where('from_uom_id', $from->id)
            ->where('to_uom_id', $to->id)
            ->first();
        if ($forward) {
            return $this->applyConversion($quantity, $forward, false);
        }

        $reverse = UomConversion::query()
            ->where('from_uom_id', $to->id)
            ->where('to_uom_id', $from->id)
            ->first();
        if ($reverse) {
            return $this->applyConversion($quantity, $reverse, true);
        }

        throw new RuntimeException('Konversi satuan tidak ditemukan.');
    }

    /**
     * Get conversion ratio (numerator, denominator) between two UOMs.
     * Used by API to return precise conversion data. Honors rules when context is supplied.
     */
    public function getConversionRatio(int $fromUomId, int $toUomId, array $context = []): array
    {
        if ($fromUomId === $toUomId) {
            return ['numerator' => 1, 'denominator' => 1];
        }

        // Rule-based ratio takes precedence when one matches
        $rule = $this->findBestRule($fromUomId, $toUomId, $context);
        if ($rule) {
            return $this->ratioFromRule($rule, false);
        }
        $reverseRule = $this->findBestRule($toUomId, $fromUomId, $context);
        if ($reverseRule) {
            return $this->ratioFromRule($reverseRule, true);
        }

        $forward = UomConversion::query()
            ->where('from_uom_id', $fromUomId)
            ->where('to_uom_id', $toUomId)
            ->first();
        if ($forward) {
            $num = $forward->numerator ?? 0;
            $den = $forward->denominator ?? 0;
            if ($num > 0 && $den > 0) {
                return ['numerator' => $num, 'denominator' => $den];
            }

            return ['numerator' => (float) $forward->factor, 'denominator' => 1];
        }

        $reverse = UomConversion::query()
            ->where('from_uom_id', $toUomId)
            ->where('to_uom_id', $fromUomId)
            ->first();
        if ($reverse) {
            $num = $reverse->numerator ?? 0;
            $den = $reverse->denominator ?? 0;
            if ($num > 0 && $den > 0) {
                return ['numerator' => $den, 'denominator' => $num];
            }
            $factor = (float) $reverse->factor;
            if ($factor > 0) {
                return ['numerator' => 1, 'denominator' => $factor];
            }
        }

        throw new RuntimeException('Konversi satuan tidak ditemukan.');
    }

    /**
     * Find the most specific rule that matches the given (from, to) and context.
     * "Most specific" = highest count of non-null scope dimensions matching the context.
     */
    private function findBestRule(int $fromUomId, int $toUomId, array $context): ?UomConversionRule
    {
        $onDate = $this->normalizeDate($context['on_date'] ?? null);

        $query = UomConversionRule::query()
            ->where('from_uom_id', $fromUomId)
            ->where('to_uom_id', $toUomId);

        // Effective window
        $query->where(fn (Builder $q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $onDate))
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $onDate));

        // Each scope dimension: rule's value must be NULL (matches any) or equal the
        // context's value at that dimension. If the context doesn't supply a value,
        // only NULL-rules can match that dimension.
        foreach (['product_id', 'variant_id', 'company_id', 'partner_id', 'context'] as $dim) {
            $value = $context[$dim] ?? null;
            $query->where(function (Builder $q) use ($dim, $value) {
                $q->whereNull($dim);
                if ($value !== null) {
                    $q->orWhere($dim, $value);
                }
            });
        }

        $candidates = $query->get();
        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates->sortByDesc(fn (UomConversionRule $r) => $this->specificityScore($r))->first();
    }

    private function specificityScore(UomConversionRule $rule): int
    {
        return ($rule->product_id !== null ? 1 : 0)
            + ($rule->variant_id !== null ? 1 : 0)
            + ($rule->company_id !== null ? 1 : 0)
            + ($rule->partner_id !== null ? 1 : 0)
            + ($rule->context !== null ? 1 : 0);
    }

    private function applyRule(float $quantity, UomConversionRule $rule, bool $reverse): float
    {
        $factor = $this->ruleForwardFactor($rule);
        $result = $reverse ? $quantity / $factor : $quantity * $factor;

        return $this->applyRounding($result, $rule->rounding_mode ?? 'nearest', (int) ($rule->decimal_places ?? self::SCALE));
    }

    /**
     * Forward factor for a rule (i.e. multiplier from `from_uom` to `to_uom`).
     */
    private function ruleForwardFactor(UomConversionRule $rule): float
    {
        if ($rule->method === 'fixed_ratio') {
            $num = (float) ($rule->numerator ?? 0);
            $den = (float) ($rule->denominator ?? 0);
            if ($num > 0 && $den > 0) {
                return $num / $den;
            }
        }

        $factor = (float) ($rule->factor ?? 0);
        if ($factor === 0.0) {
            throw new RuntimeException("Faktor konversi pada aturan #{$rule->id} bernilai nol.");
        }

        return $factor;
    }

    private function ratioFromRule(UomConversionRule $rule, bool $reverse): array
    {
        $factor = $this->ruleForwardFactor($rule);
        if ($reverse) {
            return ['numerator' => 1, 'denominator' => $factor];
        }

        // Try to preserve exact num/den for fixed_ratio when available
        if ($rule->method === 'fixed_ratio' && $rule->numerator > 0 && $rule->denominator > 0) {
            return ['numerator' => (float) $rule->numerator, 'denominator' => (float) $rule->denominator];
        }

        return ['numerator' => $factor, 'denominator' => 1];
    }

    private function applyRounding(float $value, string $mode, int $places): float
    {
        $multiplier = 10 ** $places;

        return match ($mode) {
            'ceil' => ceil($value * $multiplier) / $multiplier,
            'floor' => floor($value * $multiplier) / $multiplier,
            'truncate' => intval($value * $multiplier) / $multiplier,
            default => round($value, $places), // nearest
        };
    }

    private function normalizeDate($date): CarbonInterface
    {
        if ($date === null) {
            return Carbon::today();
        }
        if ($date instanceof CarbonInterface) {
            return $date;
        }

        return Carbon::parse($date);
    }

    /**
     * Apply conversion using numerator/denominator if available, otherwise use factor.
     */
    private function applyConversion(float $quantity, UomConversion $conversion, bool $reverse): float
    {
        $numerator = $conversion->numerator ?? 0;
        $denominator = $conversion->denominator ?? 0;

        if ($numerator > 0 && $denominator > 0) {
            if ($reverse) {
                return $this->round($quantity * $denominator / $numerator);
            }

            return $this->round($quantity * $numerator / $denominator);
        }

        $factor = (float) $conversion->factor;
        if ($factor === 0.0) {
            throw new RuntimeException('Faktor konversi tidak boleh nol.');
        }

        if ($reverse) {
            return $this->round($quantity / $factor);
        }

        return $this->round($quantity * $factor);
    }

    private function round(float $value): float
    {
        return round($value, self::SCALE);
    }
}
