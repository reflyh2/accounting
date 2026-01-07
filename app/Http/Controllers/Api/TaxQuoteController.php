<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Tax\TaxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxQuoteController extends Controller
{
    public function __construct(
        private readonly TaxService $taxService
    ) {
    }

    /**
     * Get tax quote for a product or product variant.
     * 
     * GET /api/tax-quote
     * Query params:
     *   - product_id (required if product_variant_id not provided)
     *   - product_variant_id (required if product_id not provided)
     *   - partner_id (optional)
     *   - company_id (optional)
     *   - date (optional, defaults to today)
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required_without:product_variant_id|nullable|integer|exists:products,id',
            'product_variant_id' => 'required_without:product_id|nullable|integer|exists:product_variants,id',
            'partner_id' => 'nullable|integer|exists:partners,id',
            'company_id' => 'nullable|integer|exists:companies,id',
            'date' => 'nullable|date',
        ]);

        // Get product either directly or via variant
        if ($request->input('product_variant_id')) {
            $variant = ProductVariant::with('product.taxCategory')->findOrFail(
                $request->input('product_variant_id')
            );
            $product = $variant->product;
        } else {
            $product = Product::with('taxCategory')->findOrFail(
                $request->input('product_id')
            );
        }

        $context = array_filter([
            'partner_id' => $request->input('partner_id'),
            'company_id' => $request->input('company_id'),
            'date' => $request->input('date'),
        ]);

        $quote = $this->taxService->quote($product, $context);

        return response()->json([
            'success' => true,
            'data' => [
                'rate' => $quote['rate'],
                'inclusive' => $quote['inclusive'],
                'component' => $quote['component'],
                'rule_id' => $quote['rule_id'],
                'tax_category' => $product->taxCategory?->name,
            ],
        ]);
    }
}
