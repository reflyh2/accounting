<?php

namespace App\Http\Controllers\Catalog;

use Inertia\Inertia;
use App\Models\PriceList;
use App\Models\Company;
use App\Models\Currency;
use App\Models\PartnerGroup;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class PriceListController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('price-lists.index_filters', []);
        Session::put('price-lists.index_filters', $filters);

        $query = PriceList::with(['company', 'currency', 'partnerGroup']);

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . $search . '%')
                  ->orWhere(DB::raw('lower(code)'), 'like', '%' . $search . '%');
            });
        }

        if (!empty($filters['company_id'])) {
            $query->whereIn('company_id', (array) $filters['company_id']);
        }

        if (!empty($filters['currency_id'])) {
            $query->whereIn('currency_id', (array) $filters['currency_id']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', $filters['is_active'] === '1');
        }

        $perPage = $filters['per_page'] ?? 15;
        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);

        $priceLists = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        return Inertia::render('Catalog/PriceLists/Index', [
            'priceLists' => $priceLists,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'currencies' => Currency::orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('price-lists.index_filters', []);

        return Inertia::render('Catalog/PriceLists/Create', [
            'filters' => $filters,
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'currencies' => Currency::orderBy('name')->get(['id', 'code', 'name']),
            'partnerGroups' => PartnerGroup::orderBy('name')->get(['id', 'name']),
            'channels' => self::getChannels(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'code' => 'required|string|max:50|unique:price_lists,code',
            'name' => 'required|string|max:255',
            'currency_id' => 'required|exists:currencies,id',
            'channel' => 'nullable|string|max:50',
            'partner_group_id' => 'nullable|exists:partner_groups,id',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'boolean',
        ]);

        $validated['created_by'] = $request->user()->global_id;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $priceList = PriceList::create($validated);

        return redirect()->route('catalog.price-lists.show', $priceList->id)
            ->with('success', 'Daftar harga berhasil dibuat.');
    }

    public function show(PriceList $priceList)
    {
        $filters = Session::get('price-lists.index_filters', []);
        $priceList->load(['company', 'currency', 'partnerGroup', 'items.product', 'items.productVariant', 'items.uom', 'creator', 'updater']);

        return Inertia::render('Catalog/PriceLists/Show', [
            'priceList' => $priceList,
            'filters' => $filters,
        ]);
    }

    public function edit(Request $request, PriceList $priceList)
    {
        $filters = Session::get('price-lists.index_filters', []);
        $priceList->load(['company', 'currency', 'partnerGroup']);

        return Inertia::render('Catalog/PriceLists/Edit', [
            'priceList' => $priceList,
            'filters' => $filters,
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'currencies' => Currency::orderBy('name')->get(['id', 'code', 'name']),
            'partnerGroups' => PartnerGroup::orderBy('name')->get(['id', 'name']),
            'channels' => self::getChannels(),
        ]);
    }

    public function update(Request $request, PriceList $priceList)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'code' => 'required|string|max:50|unique:price_lists,code,' . $priceList->id,
            'name' => 'required|string|max:255',
            'currency_id' => 'required|exists:currencies,id',
            'channel' => 'nullable|string|max:50',
            'partner_group_id' => 'nullable|exists:partner_groups,id',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'boolean',
        ]);

        $validated['updated_by'] = $request->user()->global_id;

        $priceList->update($validated);

        return redirect()->route('catalog.price-lists.edit', $priceList->id)
            ->with('success', 'Daftar harga berhasil diperbarui.');
    }

    public function destroy(Request $request, PriceList $priceList)
    {
        $priceList->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('catalog.price-lists.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Daftar harga berhasil dihapus.');
        }

        return Redirect::route('catalog.price-lists.index')
            ->with('success', 'Daftar harga berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        DB::transaction(function () use ($request) {
            foreach ($request->ids as $id) {
                $priceList = PriceList::find($id);
                if ($priceList) {
                    $priceList->delete();
                }
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('catalog.price-lists.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Daftar harga berhasil dihapus.');
        }

        return Redirect::route('catalog.price-lists.index')
            ->with('success', 'Daftar harga berhasil dihapus.');
    }

    public static function getChannels(): array
    {
        return \App\Enums\SalesChannel::options();
    }
}
