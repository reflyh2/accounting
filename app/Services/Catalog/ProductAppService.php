<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductCapability;
use Illuminate\Support\Facades\DB;

class ProductAppService
{
    public function createProduct(array $input, string $type): Product
    {
        return DB::transaction(function () use ($input, $type) {
            $product = Product::create([
                'code' => $input['code'],
                'name' => $input['name'],
                'kind' => $type,
                'product_category_id' => $input['product_category_id'] ?? null,
                'attribute_set_id' => $input['attribute_set_id'] ?? null,
                'attrs_json' => $input['attributes'] ?? [],
                'default_uom_id' => $input['default_uom_id'] ?? null,
                'tax_category_id' => $input['tax_category_id'] ?? null,
                'revenue_account_id' => $input['revenue_account_id'] ?? null,
                'cogs_account_id' => $input['cogs_account_id'] ?? null,
                'inventory_account_id' => $input['inventory_account_id'] ?? null,
                'is_active' => $input['is_active'] ?? true,
            ]);

            if (!empty($input['capabilities']) && is_array($input['capabilities'])) {
                foreach ($input['capabilities'] as $capability) {
                    ProductCapability::create([
                        'product_id' => $product->id,
                        'capability' => $capability,
                    ]);
                }
            }

            return $product->load(['capabilities']);
        });
    }

    public function updateProduct(Product $product, array $input, string $type): Product
    {
        return DB::transaction(function () use ($product, $input, $type) {
            $product->update([
                'code' => $input['code'] ?? $product->code,
                'name' => $input['name'] ?? $product->name,
                'kind' => $type,
                'product_category_id' => $input['product_category_id'] ?? $product->product_category_id,
                'attribute_set_id' => $input['attribute_set_id'] ?? $product->attribute_set_id,
                'attrs_json' => $input['attributes'] ?? $product->attrs_json,
                'default_uom_id' => $input['default_uom_id'] ?? $product->default_uom_id,
                'tax_category_id' => $input['tax_category_id'] ?? $product->tax_category_id,
                'revenue_account_id' => $input['revenue_account_id'] ?? $product->revenue_account_id,
                'cogs_account_id' => $input['cogs_account_id'] ?? $product->cogs_account_id,
                'inventory_account_id' => $input['inventory_account_id'] ?? $product->inventory_account_id,
                'is_active' => $input['is_active'] ?? $product->is_active,
            ]);

            if (array_key_exists('capabilities', $input) && is_array($input['capabilities'])) {
                $product->capabilities()->delete();
                foreach ($input['capabilities'] as $capability) {
                    ProductCapability::create([
                        'product_id' => $product->id,
                        'capability' => $capability,
                    ]);
                }
            }

            return $product->load(['capabilities']);
        });
    }

    public function deleteProduct(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $product->capabilities()->delete();
            $product->variants()->delete();
            $product->delete();
        });
    }
}


