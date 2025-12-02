<?php

namespace App\Services\Catalog;

use App\Models\PriceListItem;
use App\Models\PriceList;

class PricingService
{
    public function __construct(
        private readonly PriceListResolver $priceListResolver,
    ) {
    }

    public function quote(int $productId, ?int $variantId, int $uomId, float $qty, array $ctx): array
    {
        $priceList = $this->resolvePriceList($ctx);
        if (!$priceList) {
            return [
                'price' => 0.0,
                'tax' => 0.0,
                'currency' => null,
                'rule_id' => null,
            ];
        }

        $query = PriceListItem::query()
            ->where('price_list_id', $priceList->id)
            ->where('uom_id', $uomId);

        if ($variantId) {
            $query->where('product_variant_id', $variantId);
        } else {
            $query->where('product_id', $productId);
        }

        if ($qty > 0) {
            $query->where('min_qty', '<=', $qty);
        }

        $item = $query->orderBy('min_qty', 'desc')->first();
        $price = $item ? (float) $item->price : 0.0;

        return [
            'price' => $price,
            'tax' => 0.0,
            'currency' => $priceList->currency?->code,
            'rule_id' => $item?->id,
        ];
    }

    private function resolvePriceList(array $ctx): ?PriceList
    {
        if (!empty($ctx['price_list_id'])) {
            return PriceList::find($ctx['price_list_id']);
        }

        return $this->priceListResolver->resolve($ctx);
    }
}


