<?php

namespace App\Http\Controllers;

use App\Models\AssetCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use App\Exports\AssetCategoriesExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Company;

class AssetCategoryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('asset_categories.index_filters', []);
        Session::put('asset_categories.index_filters', $filters);

        $query = AssetCategory::withCount('assets');

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
        $filters = Session::get('asset_categories.index_filters', []);
        
        return Inertia::render('AssetCategories/Create', [
            'companies' => Company::orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:asset_categories,name',
            'description' => 'nullable|string',
            'company_ids' => 'required|array',
            'company_ids.*' => 'exists:companies,id',
        ]);

        $category = AssetCategory::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
        ]);

        $category->companies()->sync($validated['company_ids']);

        if ($request->input('create_another', false)) {
            return redirect()->route('asset-categories.create')
                ->with('success', 'Kategori aset berhasil dibuat. Silakan buat kategori lainnya.');
        }

        return redirect()->route('asset-categories.index')
            ->with('success', 'Kategori aset berhasil dibuat.');
    }

    public function show(AssetCategory $assetCategory)
    {
        return Inertia::render('AssetCategories/Show', [
            'category' => $assetCategory->load('companies', 'assets.branch.branchGroup.company'),
        ]);
    }

    public function edit(AssetCategory $assetCategory)
    {
        $filters = Session::get('asset_categories.index_filters', []);
        
        return Inertia::render('AssetCategories/Edit', [
            'category' => $assetCategory->load('companies'),
            'companies' => Company::orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function update(Request $request, AssetCategory $assetCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:asset_categories,name,' . $assetCategory->id,
            'description' => 'nullable|string',
            'company_ids' => 'required|array',
            'company_ids.*' => 'exists:companies,id',
        ]);

        $assetCategory->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
        ]);

        $assetCategory->companies()->sync($validated['company_ids']);

        return redirect()->route('asset-categories.index')
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