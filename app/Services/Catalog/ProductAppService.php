<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductCapability;
use App\Models\AttributeDef;
use App\Models\Uom;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductAppService
{
    public function createProduct(array $input, string $type): Product
    {
        return DB::transaction(function () use ($input, $type) {
            $product = Product::create([
                'code' => $input['code'],
                'name' => $input['name'],
                'kind' => $input['kind'] ?? $type,
                'cost_model' => $input['cost_model'] ?? 'direct_expense_per_sale',
                'product_category_id' => $input['product_category_id'] ?? null,
                'attribute_set_id' => $input['attribute_set_id'] ?? null,
                'attrs_json' => $input['attributes'] ?? [],
                'default_uom_id' => $input['default_uom_id'] ?? null,
                'tax_category_id' => $input['tax_category_id'] ?? null,
                'revenue_account_id' => $input['revenue_account_id'] ?? null,
                'cogs_account_id' => $input['cogs_account_id'] ?? null,
                'inventory_account_id' => $input['inventory_account_id'] ?? null,
                'prepaid_account_id' => $input['prepaid_account_id'] ?? null,
                'is_active' => $input['is_active'] ?? true,
            ]);

            $capabilities = $this->replaceCapabilities($product, $input['capabilities'] ?? []);
            $this->syncCompanies($product, $input['company_ids'] ?? null);
            $this->syncVariants($product, $input['attributes'] ?? [], $capabilities);

            return $product->load(['capabilities', 'companies', 'variants']);
        });
    }

    public function updateProduct(Product $product, array $input, string $type): Product
    {
        return DB::transaction(function () use ($product, $input, $type) {
            $product->update([
                'code' => $input['code'] ?? $product->code,
                'name' => $input['name'] ?? $product->name,
                'kind' => $input['kind'] ?? $type,
                'cost_model' => $input['cost_model'] ?? $product->cost_model,
                'product_category_id' => $input['product_category_id'] ?? $product->product_category_id,
                'attribute_set_id' => $input['attribute_set_id'] ?? $product->attribute_set_id,
                'attrs_json' => $input['attributes'] ?? $product->attrs_json,
                'default_uom_id' => $input['default_uom_id'] ?? $product->default_uom_id,
                'tax_category_id' => $input['tax_category_id'] ?? $product->tax_category_id,
                'revenue_account_id' => $input['revenue_account_id'] ?? $product->revenue_account_id,
                'cogs_account_id' => $input['cogs_account_id'] ?? $product->cogs_account_id,
                'inventory_account_id' => $input['inventory_account_id'] ?? $product->inventory_account_id,
                'prepaid_account_id' => $input['prepaid_account_id'] ?? $product->prepaid_account_id,
                'is_active' => $input['is_active'] ?? $product->is_active,
            ]);

            $capabilities = $this->replaceCapabilities($product, $input['capabilities'] ?? null);
            $this->syncCompanies($product, $input['company_ids'] ?? null);
            $this->syncVariants($product, $input['attributes'] ?? [], $capabilities ?? $product->capabilities->pluck('capability')->toArray());

            return $product->load(['capabilities', 'companies', 'variants']);
        });
    }

    public function deleteProduct(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $product->capabilities()->delete();
            $product->variants()->delete();
             $product->companies()->detach();
            $product->delete();
        });
    }

    private function replaceCapabilities(Product $product, ?array $capabilities): array
    {
        if (!is_array($capabilities)) {
            $product->loadMissing('capabilities');
            return $product->capabilities->pluck('capability')->toArray();
        }

        $product->capabilities()->delete();
        $normalized = [];
        $capabilities = array_values(array_unique(array_filter($capabilities)));
        foreach ($capabilities as $capability) {
            ProductCapability::create([
                'product_id' => $product->id,
                'capability' => $capability,
            ]);
            $normalized[] = $capability;
        }

        return $normalized;
    }

    private function syncCompanies(Product $product, $companyIds): void
    {
        if ($companyIds === null) {
            return;
        }
        $product->companies()->sync($companyIds);
    }

    private function syncVariants(Product $product, array $attributes, array $capabilities): void
    {
        $trackInventory = in_array('inventory_tracked', $capabilities ?? [], true);
        $uomId = $product->default_uom_id ?? $product->variants()->value('uom_id') ?? Uom::query()->value('id');

        // If no UOM available, we cannot create variants
        if (!$uomId) {
            $product->variants()->delete();
            return;
        }

        // If no attribute set, ensure a default variant exists
        if (!$product->attribute_set_id) {
            $this->ensureDefaultVariant($product, $trackInventory, $uomId);
            return;
        }

        $variantDefs = AttributeDef::query()
            ->where('attribute_set_id', $product->attribute_set_id)
            ->where('is_variant_axis', true)
            ->orderBy('id')
            ->get();

        // If no variant axes defined, ensure a default variant exists
        if ($variantDefs->isEmpty()) {
            $this->ensureDefaultVariant($product, $trackInventory, $uomId);
            return;
        }

        $axes = [];
        foreach ($variantDefs as $def) {
            $values = $attributes[$def->code] ?? null;
            if ($values === null || $values === '' || $values === []) {
                continue;
            }
            $values = is_array($values) ? $values : [$values];
            $values = array_values(array_filter($values, fn ($v) => $v !== null && $v !== ''));
            if (!empty($values)) {
                $axes[$def->code] = $values;
            }
        }

        // If axes exist but have no values, ensure a default variant exists
        if (empty($axes)) {
            $this->ensureDefaultVariant($product, $trackInventory, $uomId);
            return;
        }

        $combinations = $this->buildCombinations($axes);
        $existing = $product->variants()->get()->keyBy(function ($variant) {
            return $this->variantKey($variant->attrs_json ?? []);
        });
        $usedSkus = $existing->pluck('sku')->filter()->values()->all();
        $keptIds = [];

        foreach ($combinations as $combo) {
            $key = $this->variantKey($combo);
            $payload = [
                'attrs_json' => $combo,
                'track_inventory' => $trackInventory,
                'uom_id' => $uomId,
                'is_active' => $product->is_active,
            ];

            if ($existing->has($key)) {
                $variant = $existing[$key];
                $variant->update($payload);
                $keptIds[] = $variant->id;
            } else {
                $payload['sku'] = $this->generateSku($product, $combo, $usedSkus);
                $variant = $product->variants()->create($payload);
                $keptIds[] = $variant->id;
            }
        }

        if (!empty($keptIds)) {
            $product->variants()->whereNotIn('id', $keptIds)->delete();
        } else {
            $product->variants()->delete();
        }
    }

    /**
     * Ensure a single default variant exists for the product.
     * Used when the product has no variant axes (non-variantable products).
     */
    private function ensureDefaultVariant(Product $product, bool $trackInventory, int $uomId): void
    {
        $defaultKey = $this->variantKey([]);

        $existing = $product->variants()->get()->keyBy(function ($variant) {
            return $this->variantKey($variant->attrs_json ?? []);
        });

        $payload = [
            'attrs_json' => [],
            'track_inventory' => $trackInventory,
            'uom_id' => $uomId,
            'is_active' => $product->is_active,
        ];

        if ($existing->has($defaultKey)) {
            // Update existing default variant
            $variant = $existing[$defaultKey];
            $variant->update($payload);
            // Remove any other variants (shouldn't exist, but clean up just in case)
            $product->variants()->where('id', '!=', $variant->id)->delete();
        } else {
            // Create new default variant, delete any existing variants
            $product->variants()->delete();
            $payload['sku'] = $product->code;
            $product->variants()->create($payload);
        }
    }

    private function buildCombinations(array $axes): array
    {
        $result = [[]];
        foreach ($axes as $code => $values) {
            $next = [];
            foreach ($result as $combo) {
                foreach ($values as $value) {
                    $newCombo = $combo;
                    $newCombo[$code] = $value;
                    $next[] = $newCombo;
                }
            }
            $result = $next;
        }
        return $result;
    }

    private function variantKey(array $attrs): string
    {
        ksort($attrs);
        return json_encode($attrs);
    }

    private function generateSku(Product $product, array $combo, array &$usedSkus): string
    {
        $parts = [];
        foreach ($combo as $code => $value) {
            $valueSlug = Str::upper(Str::of($value)->slug(''));
            $parts[] = Str::upper($code) . $valueSlug;
        }
        $base = $product->code . '-' . implode('-', $parts);
        $sku = $base;
        $counter = 1;
        while (in_array($sku, $usedSkus, true)) {
            $sku = $base . '-' . $counter++;
        }
        $usedSkus[] = $sku;
        return $sku;
    }
}


