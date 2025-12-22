<?php

namespace App\Http\Controllers\Catalog;

use Inertia\Inertia;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Uom;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class PriceListItemController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('price-list-items.index_filters', []);
        Session::put('price-list-items.index_filters', $filters);

        $query = PriceListItem::with(['priceList', 'product', 'productVariant', 'uom']);

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', function ($q) use ($search) {
                    $q->where(DB::raw('lower(name)'), 'like', '%' . $search . '%')
                      ->orWhere(DB::raw('lower(code)'), 'like', '%' . $search . '%');
                });
            });
        }

        if (!empty($filters['price_list_id'])) {
            $query->whereIn('price_list_id', (array) $filters['price_list_id']);
        }

        if (!empty($filters['product_id'])) {
            $query->whereIn('product_id', (array) $filters['product_id']);
        }

        $perPage = $filters['per_page'] ?? 15;
        $sortColumn = $filters['sort'] ?? 'id';
        $sortOrder = $filters['order'] ?? 'desc';

        $query->orderBy($sortColumn, $sortOrder);

        $items = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        return Inertia::render('Catalog/PriceListItems/Index', [
            'items' => $items,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
            'priceLists' => PriceList::orderBy('name')->get(['id', 'name', 'code']),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('price-list-items.index_filters', []);

        return Inertia::render('Catalog/PriceListItems/Create', [
            'filters' => $filters,
            'priceLists' => PriceList::orderBy('name')->get(['id', 'name', 'code']),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'uoms' => Uom::orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'price_list_id' => 'required|exists:price_lists,id',
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'uom_id' => 'required|exists:uoms,id',
            'min_qty' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'tax_included' => 'boolean',
        ]);

        $validated['created_by'] = $request->user()->global_id;
        $validated['tax_included'] = $validated['tax_included'] ?? false;

        $item = PriceListItem::create($validated);

        return redirect()->route('catalog.price-list-items.index')
            ->with('success', 'Item harga berhasil dibuat.');
    }

    public function edit(Request $request, PriceListItem $priceListItem)
    {
        $filters = Session::get('price-list-items.index_filters', []);
        $priceListItem->load(['priceList', 'product', 'productVariant', 'uom']);

        // Get variants for the selected product
        $variants = [];
        if ($priceListItem->product_id) {
            $variants = ProductVariant::where('product_id', $priceListItem->product_id)
                ->orderBy('sku')
                ->get(['id', 'sku', 'barcode']);
        }

        return Inertia::render('Catalog/PriceListItems/Edit', [
            'item' => $priceListItem,
            'filters' => $filters,
            'priceLists' => PriceList::orderBy('name')->get(['id', 'name', 'code']),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'variants' => $variants,
            'uoms' => Uom::orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function update(Request $request, PriceListItem $priceListItem)
    {
        $validated = $request->validate([
            'price_list_id' => 'required|exists:price_lists,id',
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'uom_id' => 'required|exists:uoms,id',
            'min_qty' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'tax_included' => 'boolean',
        ]);

        $validated['updated_by'] = $request->user()->global_id;

        $priceListItem->update($validated);

        return redirect()->route('catalog.price-list-items.edit', $priceListItem->id)
            ->with('success', 'Item harga berhasil diperbarui.');
    }

    public function destroy(Request $request, PriceListItem $priceListItem)
    {
        $priceListItem->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('catalog.price-list-items.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Item harga berhasil dihapus.');
        }

        return Redirect::route('catalog.price-list-items.index')
            ->with('success', 'Item harga berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        DB::transaction(function () use ($request) {
            foreach ($request->ids as $id) {
                $item = PriceListItem::find($id);
                if ($item) {
                    $item->delete();
                }
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('catalog.price-list-items.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Item harga berhasil dihapus.');
        }

        return Redirect::route('catalog.price-list-items.index')
            ->with('success', 'Item harga berhasil dihapus.');
    }

    /**
     * API to get variants for a product
     */
    public function getVariants(Request $request)
    {
        $productId = $request->input('product_id');
        
        if (!$productId) {
            return response()->json([]);
        }

        $variants = ProductVariant::where('product_id', $productId)
            ->orderBy('sku')
            ->get(['id', 'sku', 'barcode']);

        return response()->json($variants);
    }
}
