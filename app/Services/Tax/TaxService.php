<?php

namespace App\Services\Tax;

use App\Models\Company;
use App\Models\Partner;
use App\Models\Product;
use App\Models\TaxJurisdiction;
use App\Models\TaxRule;
use Illuminate\Support\Carbon;

class TaxService
{
    /**
     * Get tax quote for a product based on its tax category and context.
     * 
     * @param Product $product
     * @param array $context Optional context with 'date', 'tax_jurisdiction_id', 'partner_id', 'company_id'
     * @return array{rate: float, inclusive: bool, rule_id: int|null, component: string|null}
     */
    public function quote(Product $product, array $context = []): array
    {
        if (!$product->tax_category_id) {
            return [
                'rate' => 0.0,
                'inclusive' => false,
                'rule_id' => null,
                'component' => null,
            ];
        }

        $date = $this->resolveDate($context);
        $jurisdictionId = $this->resolveJurisdictionId($context);

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

        if ($jurisdictionId) {
            $query->where('tax_jurisdiction_id', $jurisdictionId);
        }

        $rule = $query->first();

        if (!$rule) {
            return [
                'rate' => 0.0,
                'inclusive' => false,
                'rule_id' => null,
                'component' => null,
            ];
        }

        return [
            'rate' => (float) $rule->rate_value,
            'inclusive' => (bool) $rule->tax_inclusive,
            'rule_id' => $rule->id,
            'component' => $rule->component?->name,
        ];
    }

    /**
     * Resolve jurisdiction ID from context.
     * Priority: explicit jurisdiction_id > partner's country > company default
     */
    public function resolveJurisdictionId(array $context): ?int
    {
        // 1. Explicit jurisdiction ID in context
        if (!empty($context['tax_jurisdiction_id'])) {
            return (int) $context['tax_jurisdiction_id'];
        }

        // 2. Try to resolve from partner's country
        if (!empty($context['partner_id'])) {
            $partner = Partner::find($context['partner_id']);
            if ($partner?->country) {
                $jurisdiction = $this->findJurisdictionByCountry($partner->country);
                if ($jurisdiction) {
                    return $jurisdiction->id;
                }
            }
        }

        // 3. Fallback to company default
        if (!empty($context['company_id'])) {
            $company = Company::find($context['company_id']);
            if ($company?->default_tax_jurisdiction_id) {
                return $company->default_tax_jurisdiction_id;
            }
        }

        return null;
    }

    /**
     * Find jurisdiction by country name or code.
     */
    public function findJurisdictionByCountry(string $country): ?TaxJurisdiction
    {
        // Try exact match on country_code first (e.g., "ID", "US")
        $jurisdiction = TaxJurisdiction::where('country_code', strtoupper($country))
            ->where('level', 'country')
            ->first();

        if ($jurisdiction) {
            return $jurisdiction;
        }

        // Try matching country name
        $countryMappings = [
            'indonesia' => 'ID',
            'united states' => 'US',
            'usa' => 'US',
            'singapore' => 'SG',
            'malaysia' => 'MY',
            // Add more mappings as needed
        ];

        $normalizedCountry = strtolower(trim($country));
        if (isset($countryMappings[$normalizedCountry])) {
            return TaxJurisdiction::where('country_code', $countryMappings[$normalizedCountry])
                ->where('level', 'country')
                ->first();
        }

        return null;
    }

    private function resolveDate(array $context): Carbon
    {
        if (!empty($context['date'])) {
            return Carbon::parse($context['date']);
        }

        return now();
    }
}
