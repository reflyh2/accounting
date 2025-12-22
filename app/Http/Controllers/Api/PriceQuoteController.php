<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Services\Catalog\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceQuoteController extends Controller
{
    public function __construct(
        private readonly PricingService $pricingService
    ) {
    }

    /**
     * Get price quote for a product variant.
     * 
     * GET /api/price-quote
     * Query params:
     *   - product_variant_id (required)
     *   - uom_id (required)
     *   - quantity (optional, defaults to 1)
     *   - partner_id (optional) - for customer price list targeting
     *   - company_id (optional)
     *   - currency_id (optional) - to filter price lists by currency
     *   - channel (optional) - sales channel (web, pos, etc.)
     *   - date (optional, defaults to today)
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'product_variant_id' => 'required|integer|exists:product_variants,id',
            'uom_id' => 'required|integer|exists:uoms,id',
            'quantity' => 'nullable|numeric|min:0',
            'partner_id' => 'nullable|integer|exists:partners,id',
            'company_id' => 'nullable|integer|exists:companies,id',
            'currency_id' => 'nullable|integer|exists:currencies,id',
            'channel' => 'nullable|string|max:50',
            'date' => 'nullable|date',
        ]);

        $variant = ProductVariant::with('product')->findOrFail(
            $request->input('product_variant_id')
        );

        $quantity = $request->input('quantity', 1);

        $context = array_filter([
            'partner_id' => $request->input('partner_id'),
            'company_id' => $request->input('company_id'),
            'currency_id' => $request->input('currency_id'),
            'channel' => $request->input('channel'),
            'date' => $request->input('date'),
        ]);

        $quote = $this->pricingService->quote(
            productId: $variant->product_id,
            variantId: $variant->id,
            uomId: $request->input('uom_id'),
            qty: $quantity,
            ctx: $context
        );

        return response()->json([
            'success' => true,
            'data' => [
                'price' => $quote['price'],
                'currency' => $quote['currency'],
                'currency_id' => $quote['currency_id'],
                'price_list_id' => $quote['price_list_id'],
                'tax_included' => $quote['tax_included'],
                'rule_id' => $quote['rule_id'],
            ],
        ]);
    }
}
