<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Company;
use App\Models\Account;
use Illuminate\Http\Request;
use App\Models\AssetCategory;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AssetCategoriesExport;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class AssetCategoryController extends Controller
{
    /**
     * Display a listing of the asset categories.
     */
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('asset_categories.index_filters', []);
        Session::put('asset_categories.index_filters', $filters);

        $query = AssetCategory::with('companies');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(code)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(description)'), 'like', '%' . strtolower($filters['search']) . '%');
            });
        }

        if (!empty($filters['company_id'])) {
            $query->whereHas('companies', function ($q) use ($filters) {
                $q->whereIn('companies.id', $filters['company_id']);
            });
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);

        $assetCategories = $query->paginate($perPage)->onEachSide(0)->withQueryString();
        $companies = Company::orderBy('name', 'asc')->get();

        return Inertia::render('AssetCategories/Index', [
            'assetCategories' => $assetCategories,
            'companies' => $companies,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
        ]);
    }

    /**
     * Show the form for creating a new asset category.
     */
    public function create()
    {
        $filters = Session::get('asset_categories.index_filters', []);
        $companies = Company::orderBy('name', 'asc')->get();
        
        // Load accounts with their company relationships
        $accounts = Account::with('companies')
            ->where('is_parent', false)
            ->orderBy('code', 'asc')
            ->get();

        return Inertia::render('AssetCategories/Create', [
            'filters' => $filters,
            'companies' => $companies,
            'accounts' => $accounts,
        ]);
    }

    /**
     * Store a newly created asset category in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:asset_categories',
            'description' => 'nullable|string',
            'selected_companies' => 'required|array|min:1',
            'selected_companies.*' => 'required|exists:companies,id',
            'companies' => 'required|array|min:1',
            'companies.*.id' => 'required|exists:companies,id',
            'companies.*.asset_account_id' => 'nullable|exists:accounts,id',
            'companies.*.asset_depreciation_account_id' => 'nullable|exists:accounts,id',
            'companies.*.asset_accumulated_depreciation_account_id' => 'nullable|exists:accounts,id',
            'companies.*.asset_amortization_account_id' => 'nullable|exists:accounts,id',
            'companies.*.asset_prepaid_amortization_account_id' => 'nullable|exists:accounts,id',
            'companies.*.asset_rental_cost_account_id' => 'nullable|exists:accounts,id',
            'companies.*.asset_acquisition_payable_account_id' => 'nullable|exists:accounts,id',
            'companies.*.asset_sale_receivable_account_id' => 'nullable|exists:accounts,id',
            'companies.*.asset_financing_payable_account_id' => 'nullable|exists:accounts,id',
        ]);

        $assetCategory = DB::transaction(function () use ($validated) {
            $assetCategory = AssetCategory::create([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'description' => $validated['description'],
            ]);

            foreach ($validated['companies'] as $company) {
                $assetCategory->companies()->attach($company['id'], [
                    'asset_account_id' => $company['asset_account_id'] ?? null,
                    'asset_depreciation_account_id' => $company['asset_depreciation_account_id'] ?? null,
                    'asset_accumulated_depreciation_account_id' => $company['asset_accumulated_depreciation_account_id'] ?? null,
                    'asset_amortization_account_id' => $company['asset_amortization_account_id'] ?? null,
                    'asset_prepaid_amortization_account_id' => $company['asset_prepaid_amortization_account_id'] ?? null,
                    'asset_rental_cost_account_id' => $company['asset_rental_cost_account_id'] ?? null,
                    'asset_acquisition_payable_account_id' => $company['asset_acquisition_payable_account_id'] ?? null,
                    'asset_sale_receivable_account_id' => $company['asset_sale_receivable_account_id'] ?? null,
                    'asset_financing_payable_account_id' => $company['asset_financing_payable_account_id'] ?? null,
                ]);
            }

            return $assetCategory;
        });

        if ($request->input('create_another', false)) {
            return redirect()->route('asset-categories.create')
                ->with('success', 'Kategori Aset berhasil dibuat. Silakan buat Kategori Aset lainnya.');
        }

        return redirect()->route('asset-categories.show', $assetCategory->id)
            ->with('success', 'Kategori Aset berhasil dibuat.');
    }

    /**
     * Display the specified asset category.
     */
    public function show(AssetCategory $assetCategory)
    {
        $filters = Session::get('asset_categories.index_filters', []);
        
        // Load the companies with their related account data
        $assetCategory->load([
            'companies',
            'companies.accounts' => function($query) {
                $query->where('is_parent', false);
            }
        ]);
        
        // Add the account data to each company in the pivot relationship
        $assetCategory->companies->map(function($company) {
            $company->asset_account = $company->accounts->firstWhere('id', $company->pivot->asset_account_id);
            $company->asset_depreciation_account = $company->accounts->firstWhere('id', $company->pivot->asset_depreciation_account_id);
            $company->asset_accumulated_depreciation_account = $company->accounts->firstWhere('id', $company->pivot->asset_accumulated_depreciation_account_id);
            $company->asset_amortization_account = $company->accounts->firstWhere('id', $company->pivot->asset_amortization_account_id);
            $company->asset_prepaid_amortization_account = $company->accounts->firstWhere('id', $company->pivot->asset_prepaid_amortization_account_id);
            $company->asset_rental_cost_account = $company->accounts->firstWhere('id', $company->pivot->asset_rental_cost_account_id);
            $company->asset_acquisition_payable_account = $company->accounts->firstWhere('id', $company->pivot->asset_acquisition_payable_account_id);
            $company->asset_sale_receivable_account = $company->accounts->firstWhere('id', $company->pivot->asset_sale_receivable_account_id);
            $company->asset_financing_payable_account = $company->accounts->firstWhere('id', $company->pivot->asset_financing_payable_account_id);
            return $company;
        });

        return Inertia::render('AssetCategories/Show', [
            'assetCategory' => $assetCategory,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for editing the specified asset category.
     */
    public function edit(AssetCategory $assetCategory)
    {
        $filters = Session::get('asset_categories.index_filters', []);
        $assetCategory->load('companies');
        $companies = Company::orderBy('name', 'asc')->get();
        
        // Load accounts with their company relationships
        $accounts = Account::with('companies')
            ->where('is_parent', false)
            ->orderBy('code', 'asc')
            ->get();
        
        return Inertia::render('AssetCategories/Edit', [
            'assetCategory' => $assetCategory,
            'filters' => $filters,
            'companies' => $companies,
            'accounts' => $accounts,
        ]);
    }

    /**
     * Update the specified asset category in storage.
     */
    public function update(Request $request, AssetCategory $assetCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:asset_categories,code,' . $assetCategory->id,
            'description' => 'nullable|string',
            'selected_companies' => 'required|array|min:1',
            'selected_companies.*' => 'required|exists:companies,id',
            'companies' => 'required|array|min:1',
            'companies.*.id' => 'required|exists:companies,id',
            'companies.*.asset_account_id' => 'nullable|exists:accounts,id',
            'companies.*.asset_depreciation_account_id' => 'nullable|exists:accounts,id',
            'companies.*.asset_accumulated_depreciation_account_id' => 'nullable|exists:accounts,id',
            'companies.*.asset_amortization_account_id' => 'nullable|exists:accounts,id',
            'companies.*.asset_prepaid_amortization_account_id' => 'nullable|exists:accounts,id',
            'companies.*.asset_rental_cost_account_id' => 'nullable|exists:accounts,id',
            'companies.*.asset_acquisition_payable_account_id' => 'nullable|exists:accounts,id',
            'companies.*.asset_sale_receivable_account_id' => 'nullable|exists:accounts,id',
            'companies.*.asset_financing_payable_account_id' => 'nullable|exists:accounts,id',
        ]);

        DB::transaction(function () use ($validated, $assetCategory) {
            $assetCategory->update([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'description' => $validated['description'],
            ]);

            // Detach all existing companies
            $assetCategory->companies()->detach();

            // Attach updated companies with pivot data
            foreach ($validated['companies'] as $company) {
                $assetCategory->companies()->attach($company['id'], [
                    'asset_account_id' => $company['asset_account_id'] ?? null,
                    'asset_depreciation_account_id' => $company['asset_depreciation_account_id'] ?? null,
                    'asset_accumulated_depreciation_account_id' => $company['asset_accumulated_depreciation_account_id'] ?? null,
                    'asset_amortization_account_id' => $company['asset_amortization_account_id'] ?? null,
                    'asset_prepaid_amortization_account_id' => $company['asset_prepaid_amortization_account_id'] ?? null,
                    'asset_rental_cost_account_id' => $company['asset_rental_cost_account_id'] ?? null,
                    'asset_acquisition_payable_account_id' => $company['asset_acquisition_payable_account_id'] ?? null,
                    'asset_sale_receivable_account_id' => $company['asset_sale_receivable_account_id'] ?? null,
                    'asset_financing_payable_account_id' => $company['asset_financing_payable_account_id'] ?? null,
                ]);
            }
        });

        return redirect()->route('asset-categories.edit', $assetCategory->id)
            ->with('success', 'Kategori Aset berhasil diubah.');
    }

    /**
     * Remove the specified asset category from storage.
     */
    public function destroy(Request $request, AssetCategory $assetCategory)
    {
        DB::transaction(function () use ($assetCategory) {
            $assetCategory->companies()->detach();
            $assetCategory->delete();
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('asset-categories.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Kategori Aset berhasil dihapus.');
        } else {
            return Redirect::route('asset-categories.index')
                ->with('success', 'Kategori Aset berhasil dihapus.');
        }
    }

    /**
     * Bulk delete multiple asset categories.
     */
    public function bulkDelete(Request $request)
    {
        DB::transaction(function () use ($request) {
            foreach ($request->ids as $id) {
                $assetCategory = AssetCategory::find($id);
                if ($assetCategory) {
                    $assetCategory->companies()->detach();
                    $assetCategory->delete();
                }
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('asset-categories.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Kategori Aset berhasil dihapus.');
        }
    }

    /**
     * Get filtered asset categories for export.
     */
    private function getFilteredAssetCategories(Request $request)
    {
        $filters = $request->all() ?: Session::get('asset_categories.index_filters', []);

        $query = AssetCategory::with('companies');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(code)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(description)'), 'like', '%' . strtolower($filters['search']) . '%');
            });
        }

        if (!empty($filters['company_id'])) {
            $query->whereHas('companies', function ($q) use ($filters) {
                $q->whereIn('companies.id', $filters['company_id']);
            });
        }

        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);

        return $query->get();
    }

    /**
     * Export asset categories to XLSX.
     */
    public function exportXLSX(Request $request)
    {
        $assetCategories = $this->getFilteredAssetCategories($request);
        return Excel::download(new AssetCategoriesExport($assetCategories), 'asset-categories.xlsx');
    }

    /**
     * Export asset categories to CSV.
     */
    public function exportCSV(Request $request)
    {
        $assetCategories = $this->getFilteredAssetCategories($request);
        return Excel::download(new AssetCategoriesExport($assetCategories), 'asset-categories.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    /**
     * Export asset categories to PDF.
     */
    public function exportPDF(Request $request)
    {
        $assetCategories = $this->getFilteredAssetCategories($request);
        return Excel::download(new AssetCategoriesExport($assetCategories), 'asset-categories.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }
} 