<?php

namespace App\Services\Catalog;

use App\Models\AttributeDef;
use Illuminate\Support\Collection;

class AttributeService
{
    public function getDefsForSet(string $setCode): Collection
    {
        return AttributeDef::query()
            ->whereHas('attributeSet', function ($q) use ($setCode) {
                $q->where('code', $setCode);
            })
            ->orderBy('id')
            ->get();
    }

    public function validateAndNormalize(array $data, Collection $defs): array
    {
        // Minimal pass-through normalization; detailed validation can be added later
        $normalized = [];
        foreach ($defs as $def) {
            $code = $def->code;
            if (array_key_exists($code, $data)) {
                $normalized[$code] = $data[$code];
            }
        }
        return $normalized;
    }
}


