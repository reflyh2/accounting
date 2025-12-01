<?php

namespace App\Services\Catalog;

use App\Models\PriceListItem;
use App\Models\PriceList;
use Money\Money;

class PricingService
{
    /**
     * Minimal placeholder: finds first matching price list item and returns numeric price.
     */
    public function quote(int $productId, ?int $variantId, int $uomId, float $qty, array $ctx): array
    {
        $priceListId = $ctx['price_list_id'] ?? null;
        $query = PriceListItem::query()->where('uom_id', $uomId);

        if ($variantId) {
            $query->where('product_variant_id', $variantId);
        } else {
            $query->where('product_id', $productId);
        }

        if ($priceListId) {
            $query->where('price_list_id', $priceListId);
        } else {
            // pick any active price list if none provided
            $activeList = PriceList::query()->where('is_active', true)->first();
            if ($activeList) {
                $query->where('price_list_id', $activeList->id);
            }
        }

        $item = $query->orderBy('min_qty', 'desc')->first();
        $price = $item ? (float) $item->price : 0.0;

        return [
            'price' => $price,
            'tax' => 0.0,
            'currency' => $item?->priceList?->currency?->code,
            'rule_id' => $item?->id,
        ];
    }
}


