<?php

namespace App\Http\Controllers;

use App\Models\AssetCategory;
use App\Models\Company;
use App\Models\Account;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use App\Exports\AssetCategoriesExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\AssetCategoryRequest;

class AssetCategoryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('asset_categories.index_filters', []);
        Session::put('asset_categories.index_filters', $filters);

        $query = AssetCategory::query()
            ->with([
                'companies',
                'fixedAssetAccount',
                'purchasePayableAccount',
                'accumulatedDepreciationAccount',
                'depreciationExpenseAccount',
                'prepaidRentAccount',
                'rentExpenseAccount'
            ])
            ->withCount('assets');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(description)'), 'like', '%' . strtolower($filters['search']) . '%');
            });
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);

        $categories = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        return Inertia::render('AssetCategories/Index', [
            'categories' => $categories,
            'companies' => Company::orderBy('name')->get(),
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
        ]);
    }

    public function create()
    {
        return Inertia::render('AssetCategories/Create', [
            'filters' => request()->all('search', 'trashed'),
            'companies' => Company::orderBy('name')->get(),
            'accounts' => Account::where('is_parent', false)->orderBy('code')->get()
        ]);
    }

    public function store(AssetCategoryRequest $request)
    {
        DB::transaction(function () use ($request) {
            $category = AssetCategory::create([
                'name' => $request->name,
                'description' => $request->description,
                'fixed_asset_account_id' => $request->fixed_asset_account_id,
                'purchase_payable_account_id' => $request->purchase_payable_account_id,
                'accumulated_depreciation_account_id' => $request->accumulated_depreciation_account_id,
                'depreciation_expense_account_id' => $request->depreciation_expense_account_id,
                'prepaid_rent_account_id' => $request->prepaid_rent_account_id,
                'rent_expense_account_id' => $request->rent_expense_account_id,
            ]);

            if ($request->company_ids) {
                $category->companies()->sync($request->company_ids);
            }
        });

        if ($request->input('create_another', false)) {
            return redirect()->route('asset-categories.create')
                ->with('success', 'Kategori aset berhasil dibuat. Silakan buat kategori aset lainnya.');
        }

        return redirect()->route('asset-categories.show', $category->id)
            ->with('success', 'Kategori aset berhasil dibuat.');
    }

    public function show(Request $request, AssetCategory $assetCategory)
    {
        $sort = $request->sort ?? 'name';
        $order = $request->order ?? 'asc';
        $perPage = $request->per_page ?? 10;

        $assets = $assetCategory->assets()
            ->with('branch.branchGroup.company')
            ->orderBy($sort, $order)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('AssetCategories/Show', [
            'category' => $assetCategory->load([
                'companies',
                'fixedAssetAccount',
                'purchasePayableAccount',
                'accumulatedDepreciationAccount',
                'depreciationExpenseAccount',
                'prepaidRentAccount',
                'rentExpenseAccount'
            ]),
            'assets' => $assets,
            'filters' => $request->only(['sort', 'order', 'per_page']),
            'sort' => $sort,
            'order' => $order,
            'perPage' => $perPage,
        ]);
    }

    public function edit(AssetCategory $assetCategory)
    {
        return Inertia::render('AssetCategories/Edit', [
            'category' => $assetCategory->load([
                'companies',
                'fixedAssetAccount',
                'purchasePayableAccount',
                'accumulatedDepreciationAccount',
                'depreciationExpenseAccount',
                'prepaidRentAccount',
                'rentExpenseAccount'
            ]),
            'filters' => request()->all('search', 'trashed'),
            'companies' => Company::orderBy('name')->get(),
            'accounts' => Account::where('is_parent', false)->orderBy('code')->get()
        ]);
    }

    public function update(AssetCategoryRequest $request, AssetCategory $assetCategory)
    {
        DB::transaction(function () use ($request, $assetCategory) {
            $assetCategory->update([
                'name' => $request->name,
                'description' => $request->description,
                'fixed_asset_account_id' => $request->fixed_asset_account_id,
                'purchase_payable_account_id' => $request->purchase_payable_account_id,
                'accumulated_depreciation_account_id' => $request->accumulated_depreciation_account_id,
                'depreciation_expense_account_id' => $request->depreciation_expense_account_id,
                'prepaid_rent_account_id' => $request->prepaid_rent_account_id,
                'rent_expense_account_id' => $request->rent_expense_account_id,
            ]);

            if ($request->company_ids) {
                $assetCategory->companies()->sync($request->company_ids);
            }
        });

        return redirect()
            ->route('asset-categories.edit', $assetCategory->id)
            ->with('success', 'Kategori aset berhasil diubah.');
    }

    public function destroy(Request $request, AssetCategory $assetCategory)
    {
        if ($assetCategory->assets()->exists()) {
            return redirect()->route('asset-categories.index')
                ->with('error', 'Kategori tidak dapat dihapus karena masih memiliki aset.');
        }

        $assetCategory->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('asset-categories.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Kategori aset berhasil dihapus.');
        }

        return redirect()->route('asset-categories.index')
            ->with('success', 'Kategori aset berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        $categories = AssetCategory::whereIn('id', $request->ids)->withCount('assets')->get();
        
        foreach ($categories as $category) {
            if ($category->assets_count > 0) {
                return redirect()->route('asset-categories.index')
                    ->with('error', "Kategori '{$category->name}' tidak dapat dihapus karena masih memiliki aset.");
            }
        }

        AssetCategory::whereIn('id', $request->ids)->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('asset-categories.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Kategori aset berhasil dihapus.');
        }
    }

    public function exportXLSX(Request $request)
    {
        $categories = $this->getFilteredCategories($request);
        return Excel::download(new AssetCategoriesExport($categories), 'asset-categories.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $categories = $this->getFilteredCategories($request);
        return Excel::download(new AssetCategoriesExport($categories), 'asset-categories.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $categories = $this->getFilteredCategories($request);
        return Excel::download(new AssetCategoriesExport($categories), 'asset-categories.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }

    private function getFilteredCategories(Request $request)
    {
        $filters = $request->all() ?: Session::get('asset_categories.index_filters', []);

        $query = AssetCategory::withCount('assets');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(description)'), 'like', '%' . strtolower($filters['search']) . '%');
            });
        }

        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);

        return $query->get();
    }
} 