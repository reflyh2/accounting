<?php

namespace App\Services\Inventory;

use App\Models\Uom;
use App\Models\UomConversion;
use RuntimeException;

class UomConversionService
{
    private const SCALE = 6;

    /**
     * Convert quantity from one UOM to another.
     * Uses numerator/denominator for exact fractional conversion when available,
     * falls back to factor for backward compatibility.
     */
    public function convert(float $quantity, int $fromUomId, int $toUomId): float
    {
        if ($fromUomId === $toUomId) {
            return $this->round($quantity);
        }

        $from = Uom::findOrFail($fromUomId);
        $to = Uom::findOrFail($toUomId);

        if ($from->kind !== $to->kind) {
            throw new RuntimeException('Satuan tidak berada dalam jenis yang sama.');
        }

        // Try forward conversion
        $forward = UomConversion::query()
            ->where('from_uom_id', $from->id)
            ->where('to_uom_id', $to->id)
            ->first();

        if ($forward) {
            return $this->applyConversion($quantity, $forward, false);
        }

        // Try reverse conversion
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
     * Apply conversion using numerator/denominator if available, otherwise use factor.
     * 
     * @param float $quantity The quantity to convert
     * @param UomConversion $conversion The conversion record
     * @param bool $reverse If true, apply reverse conversion (divide instead of multiply)
     */
    private function applyConversion(float $quantity, UomConversion $conversion, bool $reverse): float
    {
        $numerator = $conversion->numerator ?? 0;
        $denominator = $conversion->denominator ?? 0;

        // Use numerator/denominator if both are valid (> 0)
        if ($numerator > 0 && $denominator > 0) {
            if ($reverse) {
                // Reverse: multiply by denominator, divide by numerator
                return $this->round($quantity * $denominator / $numerator);
            }
            // Forward: multiply by numerator, divide by denominator
            return $this->round($quantity * $numerator / $denominator);
        }

        // Fall back to factor
        $factor = (float) $conversion->factor;
        if ($factor === 0.0) {
            throw new RuntimeException('Faktor konversi tidak boleh nol.');
        }

        if ($reverse) {
            return $this->round($quantity / $factor);
        }
        return $this->round($quantity * $factor);
    }

    /**
     * Get conversion ratio (numerator, denominator) between two UOMs.
     * Used by API to return precise conversion data.
     */
    public function getConversionRatio(int $fromUomId, int $toUomId): array
    {
        if ($fromUomId === $toUomId) {
            return ['numerator' => 1, 'denominator' => 1];
        }

        // Try forward conversion
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
            // Fall back to factor as ratio
            return ['numerator' => (float) $forward->factor, 'denominator' => 1];
        }

        // Try reverse conversion
        $reverse = UomConversion::query()
            ->where('from_uom_id', $toUomId)
            ->where('to_uom_id', $fromUomId)
            ->first();

        if ($reverse) {
            $num = $reverse->numerator ?? 0;
            $den = $reverse->denominator ?? 0;
            if ($num > 0 && $den > 0) {
                // Swap numerator and denominator for reverse
                return ['numerator' => $den, 'denominator' => $num];
            }
            // Fall back to inverted factor
            $factor = (float) $reverse->factor;
            if ($factor > 0) {
                return ['numerator' => 1, 'denominator' => $factor];
            }
        }

        throw new RuntimeException('Konversi satuan tidak ditemukan.');
    }

    private function round(float $value): float
    {
        return round($value, self::SCALE);
    }
}



