<?php

namespace App\Services\Tax;

use App\Models\Product;
use App\Models\TaxRule;
use Illuminate\Support\Carbon;

class TaxService
{
    public function quote(Product $product, array $context = []): array
    {
        if (!$product->tax_category_id) {
            return [
                'rate' => 0.0,
                'inclusive' => false,
                'rule_id' => null,
            ];
        }

        $date = $this->resolveDate($context);

        $query = TaxRule::query()
            ->with('component')
            ->where('tax_category_id', $product->tax_category_id)
            ->where('rate_type', 'percent')
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            })
            ->orderBy('priority')
            ->orderByDesc('effective_from');

        if ($jurisdictionId = $context['tax_jurisdiction_id'] ?? null) {
            $query->where('tax_jurisdiction_id', $jurisdictionId);
        }

        $rule = $query->first();

        if (!$rule) {
            return [
                'rate' => 0.0,
                'inclusive' => false,
                'rule_id' => null,
            ];
        }

        return [
            'rate' => (float) $rule->rate_value,
            'inclusive' => (bool) $rule->tax_inclusive,
            'rule_id' => $rule->id,
            'component' => $rule->component?->name,
        ];
    }

    private function resolveDate(array $context): Carbon
    {
        if (!empty($context['date'])) {
            return Carbon::parse($context['date']);
        }

        return now();
    }
}


