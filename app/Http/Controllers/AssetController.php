<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Branch;
use App\Models\Company;
use App\Models\AssetCategory;
use App\Exports\AssetsExport;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $branches = Branch::orderBy('name')->get();
        $companies = Company::orderBy('name')->get();
        $categories = AssetCategory::orderBy('name')->get();
        
        $assets = $this->getFilteredAssets($request);

        return Inertia::render('Assets/Index', [
            'assets' => $assets,
            'filters' => $request->all(),
            'companies' => $companies,
            'branches' => $branches,
            'categories' => $categories,
            'perPage' => $request->input('per_page', 10),
            'sort' => $request->input('sort', 'acquisition_date'),
            'order' => $request->input('order', 'desc'),
        ]);
    }

    public function create(Request $request)
    {
        $companies = Company::orderBy('name')->get();
        $categories = AssetCategory::orderBy('name')->get();

        return Inertia::render('Assets/Create', [
            'filters' => $request->all(),
            'companies' => $companies,
            'branches' => fn() => Branch::whereHas('branchGroup', function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            })->orderBy('name', 'asc')->get(),
            'categories' => $categories,
            'assetTypes' => Asset::assetTypes(),
            'acquisitionTypes' => Asset::acquisitionTypes(),
            'depreciationMethods' => Asset::depreciationMethods(),
            'statusOptions' => Asset::statusOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'branch_id' => 'required|exists:branches,id',
            'asset_category_id' => 'required|exists:asset_categories,id',
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:' . implode(',', array_keys(Asset::assetTypes())),
            'acquisition_type' => 'required|string|in:' . implode(',', array_keys(Asset::acquisitionTypes())),
            'acquisition_date' => 'nullable|date',
            'cost_basis' => 'nullable|numeric|min:0',
            'salvage_value' => 'nullable|numeric|min:0',
            'is_depreciable' => 'boolean',
            'is_amortizable' => 'boolean',
            'depreciation_method' => 'required|string|in:' . implode(',', array_keys(Asset::depreciationMethods())),
            'useful_life_months' => 'nullable|integer|min:0',
            'depreciation_start_date' => 'nullable|date',
            'accumulated_depreciation' => 'nullable|numeric|min:0',
            'net_book_value' => 'nullable|numeric|min:0',
            'status' => 'required|string|in:' . implode(',', array_keys(Asset::statusOptions())),
            'notes' => 'nullable|string',
            'warranty_expiry' => 'nullable|date',
        ]);

        $user = User::find(Auth::user()->global_id);
        
        // Add the user who created this asset
        $validated['created_by'] = $user->global_id;
        $validated['updated_by'] = $user->global_id;

        // Calculate net book value if not provided
        if (!isset($validated['net_book_value'])) {
            $validated['net_book_value'] = isset($validated['cost_basis']) ? 
                ($validated['cost_basis'] - ($validated['accumulated_depreciation'] ?? 0)) : 0;
        }

        $asset = Asset::create($validated);

        $redirect = $request->input('create_another', false) ? 
            route('assets.create') : route('assets.index');

        return redirect($redirect)->with('success', 'Aset berhasil ditambahkan');
    }

    public function show(Asset $asset)
    {
        $asset->load(['company', 'branch', 'category', 'createdByUser', 'updatedByUser']);

        return Inertia::render('Assets/Show', [
            'asset' => $asset,
            'filters' => request()->all('search', 'trashed'),
            'assetTypes' => Asset::assetTypes(),
            'acquisitionTypes' => Asset::acquisitionTypes(),
            'depreciationMethods' => Asset::depreciationMethods(),
            'statusOptions' => Asset::statusOptions(),
        ]);
    }

    public function edit(Request $request, $assetId)
    {
        $asset = Asset::find($assetId);
        $companies = Company::orderBy('name')->get();
        $categories = AssetCategory::orderBy('name')->get();

        return Inertia::render('Assets/Edit', [
            'asset' => $asset,
            'filters' => request()->all('search', 'trashed'),
            'companies' => $companies,
            'branches' => Branch::whereHas('branchGroup', function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            })->orderBy('name', 'asc')->get(),
            'categories' => $categories,
            'assetTypes' => Asset::assetTypes(),
            'acquisitionTypes' => Asset::acquisitionTypes(),
            'depreciationMethods' => Asset::depreciationMethods(),
            'statusOptions' => Asset::statusOptions(),
        ]);
    }

    public function update(Request $request, Asset $asset)
    {
        $validated = $request->validate([
            'company_id' => 'sometimes|required|exists:companies,id',
            'branch_id' => 'sometimes|required|exists:branches,id',
            'asset_category_id' => 'required|exists:asset_categories,id',
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:' . implode(',', array_keys(Asset::assetTypes())),
            'acquisition_type' => 'required|string|in:' . implode(',', array_keys(Asset::acquisitionTypes())),
            'acquisition_date' => 'nullable|date',
            'cost_basis' => 'nullable|numeric|min:0',
            'salvage_value' => 'nullable|numeric|min:0',
            'is_depreciable' => 'boolean',
            'is_amortizable' => 'boolean',
            'depreciation_method' => 'required|string|in:' . implode(',', array_keys(Asset::depreciationMethods())),
            'useful_life_months' => 'nullable|integer|min:0',
            'depreciation_start_date' => 'nullable|date',
            'accumulated_depreciation' => 'nullable|numeric|min:0',
            'net_book_value' => 'nullable|numeric|min:0',
            'status' => 'required|string|in:' . implode(',', array_keys(Asset::statusOptions())),
            'notes' => 'nullable|string',
            'warranty_expiry' => 'nullable|date',
        ]);

        $user = User::find(Auth::user()->global_id);
        
        // Add the user who updated this asset
        $validated['updated_by'] = $user->global_id;

        // Calculate net book value if not provided
        if (!isset($validated['net_book_value'])) {
            $validated['net_book_value'] = isset($validated['cost_basis']) ? 
                ($validated['cost_basis'] - ($validated['accumulated_depreciation'] ?? 0)) : 0;
        }

        $asset->update($validated);

        return redirect()->route('assets.index')->with('success', 'Aset berhasil diperbarui');
    }

    public function destroy(Asset $asset)
    {
        $asset->delete();

        return redirect()->route('assets.index')->with('success', 'Aset berhasil dihapus');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:assets,id',
        ]);

        Asset::whereIn('id', $validated['ids'])->delete();

        return redirect()->route('assets.index')->with('success', 'Aset-aset berhasil dihapus');
    }

    private function getFilteredAssets(Request $request)
    {
        $query = Asset::with(['company', 'branch', 'category']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('code', 'like', '%' . $search . '%')
                  ->orWhere('notes', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('company_id')) {
            $query->whereIn('company_id', (array) $request->input('company_id'));
        }

        if ($request->filled('branch_id')) {
            $query->whereIn('branch_id', (array) $request->input('branch_id'));
        }

        if ($request->filled('asset_category_id')) {
            $query->whereIn('asset_category_id', (array) $request->input('asset_category_id'));
        }

        if ($request->filled('type')) {
            $query->whereIn('type', (array) $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->whereIn('status', (array) $request->input('status'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('acquisition_date', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('acquisition_date', '<=', $request->input('to_date'));
        }

        // Default sorting by acquisition_date in descending order
        $sort = $request->input('sort', 'acquisition_date');
        $order = $request->input('order', 'desc');
        
        // Handle relationship sorting
        if (in_array($sort, ['company.name', 'branch.name', 'category.name'])) {
            $relation = explode('.', $sort)[0];
            $relationColumn = explode('.', $sort)[1];
            
            $query->join($relation . 's', 'assets.' . $relation . '_id', '=', $relation . 's.id')
                  ->select('assets.*')
                  ->orderBy($relation . 's.' . $relationColumn, $order);
        } else {
            $query->orderBy($sort, $order);
        }

        return $query->paginate($request->input('per_page', 10))->withQueryString();
    }

    private function getBranchesByCompany($companyId = null)
    {
        if ($companyId) {
            return Branch::whereHas('branchGroup', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })->orderBy('name')->get();
        }
        
        return Branch::orderBy('name')->get();
    }

    public function exportXLSX(Request $request)
    {
        $assets = $this->getExportData($request);
        return Excel::download(new AssetsExport($assets), 'assets.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $assets = $this->getExportData($request);
        return Excel::download(new AssetsExport($assets), 'assets.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $assets = $this->getExportData($request);
        $pdf = PDF::loadView('exports.assets', ['assets' => $assets]);
        return $pdf->download('assets.pdf');
    }

    public function print(Asset $asset)
    {
        $asset->load(['company', 'branch', 'category']);
        $pdf = PDF::loadView('prints.asset', ['asset' => $asset]);
        return $pdf->stream('asset-' . $asset->code . '.pdf');
    }

    private function getExportData(Request $request)
    {
        return $this->getFilteredAssets($request)->getCollection();
    }
} 