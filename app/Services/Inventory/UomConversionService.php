<?php

namespace App\Services\Inventory;

use App\Models\Uom;
use App\Models\UomConversion;
use RuntimeException;

class UomConversionService
{
    private const SCALE = 6;

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

        $forwardFactor = UomConversion::query()
            ->where('from_uom_id', $from->id)
            ->where('to_uom_id', $to->id)
            ->value('factor');

        if ($forwardFactor) {
            return $this->round($quantity * (float) $forwardFactor);
        }

        $reverseFactor = UomConversion::query()
            ->where('from_uom_id', $to->id)
            ->where('to_uom_id', $from->id)
            ->value('factor');

        if ($reverseFactor) {
            if ((float) $reverseFactor === 0.0) {
                throw new RuntimeException('Faktor konversi tidak boleh nol.');
            }

            return $this->round($quantity / (float) $reverseFactor);
        }

        throw new RuntimeException('Konversi satuan tidak ditemukan.');
    }

    private function round(float $value): float
    {
        return round($value, self::SCALE);
    }
}


