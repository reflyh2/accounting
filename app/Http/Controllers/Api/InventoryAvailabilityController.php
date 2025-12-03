<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryAvailabilityController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'branch_id' => ['required', 'exists:branches,id'],
        ]);

        $locationIds = Location::query()
            ->where('branch_id', $data['branch_id'])
            ->pluck('id');

        if ($locationIds->isEmpty()) {
            return response()->json([
                'on_hand' => 0,
                'reserved' => 0,
                'available' => 0,
            ]);
        }

        $items = InventoryItem::query()
            ->where('product_variant_id', $data['product_variant_id'])
            ->whereIn('location_id', $locationIds)
            ->get();

        $onHand = $items->sum(fn ($item) => (float) $item->qty_on_hand);
        $reserved = $items->sum(fn ($item) => (float) $item->qty_reserved);
        $available = max(0, $onHand - $reserved);

        return response()->json([
            'on_hand' => round($onHand, 3),
            'reserved' => round($reserved, 3),
            'available' => round($available, 3),
        ]);
    }
}


