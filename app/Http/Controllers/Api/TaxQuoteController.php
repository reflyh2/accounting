<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
     * Get tax quote for a product variant.
     * 
     * GET /api/tax-quote
     * Query params:
     *   - product_variant_id (required)
     *   - partner_id (optional)
     *   - company_id (optional)
     *   - date (optional, defaults to today)
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'product_variant_id' => 'required|integer|exists:product_variants,id',
            'partner_id' => 'nullable|integer|exists:partners,id',
            'company_id' => 'nullable|integer|exists:companies,id',
            'date' => 'nullable|date',
        ]);

        $variant = ProductVariant::with('product.taxCategory')->findOrFail(
            $request->input('product_variant_id')
        );

        $context = array_filter([
            'partner_id' => $request->input('partner_id'),
            'company_id' => $request->input('company_id'),
            'date' => $request->input('date'),
        ]);

        $quote = $this->taxService->quote($variant->product, $context);

        return response()->json([
            'success' => true,
            'data' => [
                'rate' => $quote['rate'],
                'inclusive' => $quote['inclusive'],
                'component' => $quote['component'],
                'rule_id' => $quote['rule_id'],
                'tax_category' => $variant->product->taxCategory?->name,
            ],
        ]);
    }
}
