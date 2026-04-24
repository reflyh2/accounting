<?php

namespace App\Services\Asset;

/**
 * Computes per-month depreciation amounts for an asset. Pure functions only.
 *
 * Logic mirrors what AssetPurchaseEventSubscriber does when generating
 * schedules from an AssetInvoice — extracted here so the asset importer
 * can produce identical schedules without going through the invoice flow.
 */
class AssetDepreciationCalculator
{
    /**
     * Returns an array of length $months containing the monthly depreciation
     * amount (in the asset's currency). Months are zero-indexed.
     *
     * @return float[]
     */
    public static function amounts(string $method, float $cost, float $salvage, int $months): array
    {
        if ($months <= 0 || $cost <= 0) {
            return [];
        }

        return match ($method) {
            'straight-line' => self::straightLine($cost, $salvage, $months),
            'declining-balance' => self::decliningBalance($cost, $salvage, $months),
            'sum-of-years-digits' => self::sumOfYearsDigits($cost, $salvage, $months),
            'units-of-production' => self::straightLine($cost, $salvage, $months), // pragmatic fallback
            default => array_fill(0, $months, 0.0),
        };
    }

    private static function straightLine(float $cost, float $salvage, int $months): array
    {
        $base = max(0, $cost - $salvage);
        $per = round($base / $months, 2);
        $amounts = array_fill(0, $months, $per);
        $amounts[$months - 1] = round($base - $per * ($months - 1), 2);

        return $amounts;
    }

    private static function decliningBalance(float $cost, float $salvage, int $months): array
    {
        $base = max(0, $cost - $salvage);
        $rate = 2 / $months;
        $bookValue = $cost;
        $amounts = [];
        for ($i = 0; $i < $months; $i++) {
            $depr = round($bookValue * $rate, 2);
            if ($i === $months - 1) {
                $depr = round($base - array_sum($amounts), 2);
            }
            $amounts[] = max(0.0, $depr);
            $bookValue = max($salvage, $bookValue - $depr);
        }

        return $amounts;
    }

    private static function sumOfYearsDigits(float $cost, float $salvage, int $months): array
    {
        $base = max(0, $cost - $salvage);
        $sum = ($months * ($months + 1)) / 2;
        $amounts = [];
        for ($i = 0; $i < $months; $i++) {
            $fraction = ($months - $i) / $sum;
            $amounts[] = round($base * $fraction, 2);
        }
        $amounts[$months - 1] = round($base - array_sum(array_slice($amounts, 0, $months - 1)), 2);

        return $amounts;
    }
}
