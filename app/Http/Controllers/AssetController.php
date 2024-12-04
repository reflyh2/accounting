<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Http\Request;
use App\Exports\AssetsExport;
use App\Models\AssetCategory;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('assets.index_filters', []);
        Session::put('assets.index_filters', $filters);

        $query = Asset::with('category', 'branch.branchGroup.company');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(serial_number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(supplier)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('category', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  });
            });
        }

        if (!empty($filters['category_id'])) {
            $query->whereIn('category_id', $filters['category_id']);
        }

        if (!empty($filters['company_id'])) {
            $query->whereHas('branch', function ($query) use ($filters) {
                $query->whereHas('branchGroup', function ($query) use ($filters) {
                    $query->whereIn('company_id', $filters['company_id']);
                });
            });
        }

        if (!empty($filters['branch_id'])) {
            $query->whereIn('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('purchase_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('purchase_date', '<=', $filters['to_date']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);

        $companies = Company::orderBy('name', 'asc')->get();

        if (!empty($filters['company_id'])) {
            $branches = Branch::whereHas('branchGroup', function ($query) use ($filters) {
               $query->whereIn('company_id', $filters['company_id']);
            })->get();
        } else {
            $branches = Branch::orderBy('name', 'asc')->get();
        }

        $assets = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $categories = AssetCategory::orderBy('name', 'asc')->get();

        return Inertia::render('Assets/Index', [
            'companies' => $companies,
            'branches' => $branches,
            'assets' => $assets,
            'categories' => $categories,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('assets.index_filters', []);
        
        return Inertia::render('Assets/Create', [
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => fn() => Branch::whereHas('branchGroup', function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            })->orderBy('name', 'asc')->get(),
            'categories' => fn() => AssetCategory::whereHas('companies', function ($query) use ($request) {
                $query->where('companies.id', $request->input('company_id'));
            })->orderBy('name', 'asc')->get(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:asset_categories,id',
            'serial_number' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive,maintenance,disposed',
            'purchase_cost' => 'required|numeric|min:0',
            'purchase_date' => 'required|date',
            'supplier' => 'nullable|string|max:255',
            'warranty_expiry' => 'nullable|date',
            'location' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'depreciation_method' => 'required|in:straight-line,declining-balance',
            'useful_life_months' => 'required|integer|min:1',
            'salvage_value' => 'required|numeric|min:0',
            'notes' => 'nullable|string'
        ]);

        $asset = Asset::create($validated);

        if ($request->input('create_another', false)) {
            return redirect()->route('assets.create')
                ->with('success', 'Asset berhasil dibuat. Anda dapat membuat aset lainnya.');
        }

        return redirect()->route('assets.show', $asset->id)
            ->with('success', 'Asset berhasil dibuat.');
    }

    public function show(Request $request, Asset $asset)
    {
        $filters = Session::get('assets.index_filters', []);
        $asset->load('category', 'branch.branchGroup.company', 'maintenanceRecords');
        
        return Inertia::render('Assets/Show', [
            'asset' => $asset,
            'filters' => $filters,
        ]);
    }

    public function edit(Request $request, Asset $asset)
    {
        $filters = Session::get('assets.index_filters', []);

        $companyId = $asset->branch->branchGroup->company_id;
        
        if ($request->company_id) {
            $companyId = $request->company_id;
        }
        
        return Inertia::render('Assets/Edit', [
            'asset' => $asset->load(['category', 'branch.branchGroup.company']),
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => Branch::whereHas('branchGroup', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name', 'asc')->get(),
            'categories' => AssetCategory::whereHas('companies', function ($query) use ($companyId) {
                $query->where('companies.id', $companyId);
            })->orderBy('name', 'asc')->get(),
            'filters' => $filters,
        ]);
    }

    public function update(Request $request, Asset $asset)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:asset_categories,id',
            'serial_number' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive,maintenance,disposed',
            'purchase_cost' => 'required|numeric|min:0',
            'purchase_date' => 'required|date',
            'supplier' => 'nullable|string|max:255',
            'warranty_expiry' => 'nullable|date',
            'location' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'depreciation_method' => 'required|in:straight-line,declining-balance',
            'useful_life_months' => 'required|integer|min:1',
            'salvage_value' => 'required|numeric|min:0',
            'notes' => 'nullable|string'
        ]);

        $asset->update($validated);

        return redirect()->route('assets.edit', $asset->id)
            ->with('success', 'Asset berhasil diubah.');
    }

    public function destroy(Request $request, Asset $asset)
    {
        $asset->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('assets.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Asset berhasil dihapus.');
        } else {
            return Redirect::route('assets.index')
                ->with('success', 'Asset berhasil dihapus.');
        }
    }

    public function bulkDelete(Request $request)
    {
        Asset::whereIn('id', $request->ids)->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('assets.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Assets berhasil dihapus.');
        }
    }

    private function getFilteredAssets(Request $request)
    {
        $filters = $request->all() ?: Session::get('assets.index_filters', []);

        $query = Asset::with('category');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(serial_number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(supplier)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('category', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  });
            });
        }

        if (!empty($filters['category_id'])) {
            $query->whereIn('category_id', $filters['category_id']);
        }

        if (!empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('purchase_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('purchase_date', '<=', $filters['to_date']);
        }

        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $assets = $this->getFilteredAssets($request);
        return Excel::download(new AssetsExport($assets), 'assets.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $assets = $this->getFilteredAssets($request);
        return Excel::download(new AssetsExport($assets), 'assets.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $assets = $this->getFilteredAssets($request);
        return Excel::download(new AssetsExport($assets), 'assets.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }

    public function print(Asset $asset)
    {
        return Inertia::render('Assets/Print', [
            'asset' => $asset->load('category'),
        ]);
    }
} 