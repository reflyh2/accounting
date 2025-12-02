<?php

namespace App\Http\Controllers\Catalog;

use App\Models\Currency;
use App\Models\Product;
use Inertia\Inertia;

class RentalProductController extends BaseProductController
{
    protected string $type = 'rental';

    public function show($id)
    {
        $product = Product::with([
            'category',
            'defaultUom',
            'taxCategory',
            'companies',
            'variants',
            'resourcePools.branch',
            'resourcePools.instances',
            'rentalPolicy',
        ])->findOrFail($id);

        abort_unless($product->kind === $this->type, 404);

        return Inertia::render($this->viewBase().'/Show', [
            'product' => $product,
            'currencies' => Currency::orderBy('code')->get(['id', 'code', 'name', 'symbol']),
            'partnerSearchUrl' => route('api.partners'),
        ]);
    }

    protected function viewBase(): string
    {
        return 'Catalog/Rental';
    }

    protected function routeBase(): string
    {
        return 'catalog.rental';
    }
}


