<?php

namespace App\Http\Controllers;

use App\Enums\ShippingProviderType;
use App\Exports\ShippingProvidersExport;
use App\Models\ShippingProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ShippingProviderController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('shipping_providers.index_filters', []);
        Session::put('shipping_providers.index_filters', $filters);

        $query = ShippingProvider::query();

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(code)'), 'like', '%'.strtolower($filters['search']).'%')
                    ->orWhere(DB::raw('lower(name)'), 'like', '%'.strtolower($filters['search']).'%')
                    ->orWhere(DB::raw('lower(contact_person)'), 'like', '%'.strtolower($filters['search']).'%')
                    ->orWhere(DB::raw('lower(phone)'), 'like', '%'.strtolower($filters['search']).'%');
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', $filters['is_active']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'code';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);
        $shippingProviders = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        return Inertia::render('ShippingProviders/Index', [
            'shippingProviders' => $shippingProviders,
            'filters' => $filters,
            'perPage' => $perPage,
            'sortColumn' => $sortColumn,
            'sortOrder' => $sortOrder,
            'typeOptions' => ShippingProviderType::options(),
        ]);
    }

    public function create()
    {
        $filters = Session::get('shipping_providers.index_filters', []);

        return Inertia::render('ShippingProviders/Create', [
            'filters' => $filters,
            'typeOptions' => ShippingProviderType::options(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50|unique:shipping_providers,code',
            'name' => 'required|string|max:120',
            'type' => 'required|string|in:internal,external',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:120',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $shippingProvider = ShippingProvider::create([
            'code' => $validated['code'] ?? null,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'contact_person' => $validated['contact_person'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
        ]);

        if ($request->input('create_another')) {
            return redirect()->route('shipping-providers.create')->with('success', 'Penyedia pengiriman berhasil ditambahkan.');
        } else {
            return redirect()->route('shipping-providers.index')->with('success', 'Penyedia pengiriman berhasil ditambahkan.');
        }
    }

    public function show(ShippingProvider $shippingProvider)
    {
        $filters = Session::get('shipping_providers.index_filters', []);
        $shippingProvider->load(['creator', 'updater']);

        return Inertia::render('ShippingProviders/Show', [
            'shippingProvider' => $shippingProvider,
            'filters' => $filters,
            'typeOptions' => ShippingProviderType::options(),
        ]);
    }

    public function edit(ShippingProvider $shippingProvider)
    {
        $filters = Session::get('shipping_providers.index_filters', []);

        return Inertia::render('ShippingProviders/Edit', [
            'shippingProvider' => $shippingProvider,
            'filters' => $filters,
            'typeOptions' => ShippingProviderType::options(),
        ]);
    }

    public function update(Request $request, ShippingProvider $shippingProvider)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:shipping_providers,code,'.$shippingProvider->id,
            'name' => 'required|string|max:120',
            'type' => 'required|string|in:internal,external',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:120',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $shippingProvider->update([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'contact_person' => $validated['contact_person'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : $shippingProvider->is_active,
        ]);

        return redirect()->route('shipping-providers.edit', $shippingProvider->id)->with('success', 'Penyedia pengiriman berhasil diubah.');
    }

    public function destroy(Request $request, ShippingProvider $shippingProvider)
    {
        $inUseCount = DB::table('sales_orders')
            ->where('shipping_provider_id', $shippingProvider->id)
            ->count()
            + DB::table('sales_deliveries')
                ->where('shipping_provider_id', $shippingProvider->id)
                ->count();

        if ($inUseCount > 0) {
            return redirect()->back()->with(['error' => 'Penyedia pengiriman tidak dapat dihapus karena sedang digunakan.']);
        }

        $shippingProvider->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('shipping-providers.index').($currentQuery ? '?'.$currentQuery : '');

            return Redirect::to($redirectUrl)
                ->with('success', 'Penyedia pengiriman berhasil dihapus.');
        } else {
            return Redirect::route('shipping-providers.index')
                ->with('success', 'Penyedia pengiriman berhasil dihapus.');
        }
    }

    public function bulkDelete(Request $request)
    {
        $inUseCount = DB::table('sales_orders')
            ->whereIn('shipping_provider_id', $request->ids)
            ->count()
            + DB::table('sales_deliveries')
                ->whereIn('shipping_provider_id', $request->ids)
                ->count();

        if ($inUseCount > 0) {
            return redirect()->back()->with(['error' => 'Beberapa penyedia pengiriman tidak dapat dihapus karena sedang digunakan.']);
        }

        ShippingProvider::whereIn('id', $request->ids)->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('shipping-providers.index').($currentQuery ? '?'.$currentQuery : '');

            return Redirect::to($redirectUrl)
                ->with('success', 'Penyedia pengiriman berhasil dihapus.');
        } else {
            return Redirect::route('shipping-providers.index')
                ->with('success', 'Penyedia pengiriman berhasil dihapus.');
        }
    }

    private function getFilteredShippingProviders(Request $request)
    {
        $query = ShippingProvider::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where(DB::raw('lower(code)'), 'like', '%'.strtolower($request->search).'%')
                    ->orWhere(DB::raw('lower(name)'), 'like', '%'.strtolower($request->search).'%')
                    ->orWhere(DB::raw('lower(contact_person)'), 'like', '%'.strtolower($request->search).'%')
                    ->orWhere(DB::raw('lower(phone)'), 'like', '%'.strtolower($request->search).'%');
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $shippingProviders = $this->getFilteredShippingProviders($request);

        return Excel::download(new ShippingProvidersExport($shippingProviders), 'shipping-providers.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $shippingProviders = $this->getFilteredShippingProviders($request);

        return Excel::download(new ShippingProvidersExport($shippingProviders), 'shipping-providers.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $shippingProviders = $this->getFilteredShippingProviders($request);

        return Excel::download(new ShippingProvidersExport($shippingProviders), 'shipping-providers.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }
}
