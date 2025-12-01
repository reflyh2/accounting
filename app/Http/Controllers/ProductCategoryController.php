<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Company;
use App\Models\AttributeSet;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProductCategoriesExport;

class ProductCategoryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('product-categories.index_filters', []);
        Session::put('product-categories.index_filters', $filters);

        $query = ProductCategory::query()->with(['company', 'parent', 'attributeSet']);

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where(DB::raw('lower(code)'), 'like', '%' . $search . '%')
                    ->orWhere(DB::raw('lower(name)'), 'like', '%' . $search . '%');
            });
        }

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (!empty($filters['attribute_set_id'])) {
            $query->where('attribute_set_id', $filters['attribute_set_id']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'sort_order';
        $sortOrder = $filters['order'] ?? 'asc';

        $allowedSorts = ['code', 'name', 'sort_order', 'created_at'];
        if (!in_array($sortColumn, $allowedSorts)) {
            $sortColumn = 'sort_order';
        }

        if (!in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'asc';
        }

        $query->orderBy($sortColumn, $sortOrder);

        $categories = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        return Inertia::render('ProductCategories/Index', [
            'categories' => $categories,
            'filters' => $filters,
            'perPage' => $perPage,
            'sortColumn' => $sortColumn,
            'sortOrder' => $sortOrder,
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'attributeSets' => AttributeSet::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        $filters = Session::get('product-categories.index_filters', []);

        return Inertia::render('ProductCategories/Create', [
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'attributeSets' => AttributeSet::orderBy('name')->get(['id', 'name']),
            'parentCategories' => ProductCategory::orderBy('name')->get(['id', 'name', 'company_id']),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);

        $userGlobalId = Auth::user()?->global_id;

        DB::transaction(function () use ($validated, $userGlobalId) {
            $parent = isset($validated['parent_id']) ? ProductCategory::find($validated['parent_id']) : null;

            ProductCategory::create([
                'company_id' => $validated['company_id'],
                'attribute_set_id' => $validated['attribute_set_id'],
                'parent_id' => $validated['parent_id'] ?? null,
                'code' => $validated['code'],
                'name' => $validated['name'],
                'sort_order' => $validated['sort_order'] ?? 0,
                'path' => $validated['path'] ?? $this->buildCategoryPath($validated['code'], $parent),
                'created_by' => $userGlobalId,
                'updated_by' => $userGlobalId,
            ]);
        });

        if ($request->boolean('create_another')) {
            return redirect()->route('catalog.product-categories.create')
                ->with('success', 'Kategori produk berhasil ditambahkan.');
        }

        return redirect()->route('catalog.product-categories.index')
            ->with('success', 'Kategori produk berhasil ditambahkan.');
    }

    public function show(ProductCategory $productCategory)
    {
        $filters = Session::get('product-categories.index_filters', []);
        $productCategory->load(['company', 'parent', 'attributeSet', 'children']);

        return Inertia::render('ProductCategories/Show', [
            'category' => $productCategory,
            'filters' => $filters,
        ]);
    }

    public function edit(ProductCategory $productCategory)
    {
        $filters = Session::get('product-categories.index_filters', []);
        $productCategory->load(['company', 'parent', 'attributeSet']);

        return Inertia::render('ProductCategories/Edit', [
            'category' => $productCategory,
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'attributeSets' => AttributeSet::orderBy('name')->get(['id', 'name']),
            'parentCategories' => ProductCategory::where('id', '!=', $productCategory->id)
                ->orderBy('name')
                ->get(['id', 'name', 'company_id']),
            'filters' => $filters,
        ]);
    }

    public function update(Request $request, ProductCategory $productCategory)
    {
        $validated = $this->validateRequest($request, $productCategory);

        $userGlobalId = Auth::user()?->global_id;

        DB::transaction(function () use ($validated, $productCategory, $userGlobalId) {
            $parent = isset($validated['parent_id']) ? ProductCategory::find($validated['parent_id']) : null;

            $productCategory->update([
                'company_id' => $validated['company_id'],
                'attribute_set_id' => $validated['attribute_set_id'],
                'parent_id' => $validated['parent_id'] ?? null,
                'code' => $validated['code'],
                'name' => $validated['name'],
                'sort_order' => $validated['sort_order'] ?? 0,
                'path' => $validated['path'] ?? $this->buildCategoryPath($validated['code'], $parent),
                'updated_by' => $userGlobalId,
            ]);
        });

        return redirect()->route('catalog.product-categories.edit', $productCategory->id)
            ->with('success', 'Kategori produk berhasil diubah.');
    }

    public function destroy(Request $request, ProductCategory $productCategory)
    {
        if ($productCategory->children()->exists()) {
            return redirect()->back()->with('error', 'Kategori memiliki sub kategori dan tidak dapat dihapus.');
        }

        if ($productCategory->products()->exists()) {
            return redirect()->back()->with('error', 'Kategori sedang digunakan oleh produk.');
        }

        $productCategory->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('catalog.product-categories.index') . ($currentQuery ? '?' . $currentQuery : '');

            return Redirect::to($redirectUrl)->with('success', 'Kategori produk berhasil dihapus.');
        }

        return Redirect::route('catalog.product-categories.index')->with('success', 'Kategori produk berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:product_categories,id'],
        ]);

        $hasChildren = ProductCategory::whereIn('id', $request->ids)
            ->whereHas('children')
            ->exists();

        if ($hasChildren) {
            return redirect()->back()->with('error', 'Beberapa kategori memiliki sub kategori dan tidak dapat dihapus.');
        }

        $hasProducts = ProductCategory::whereIn('id', $request->ids)
            ->whereHas('products')
            ->exists();

        if ($hasProducts) {
            return redirect()->back()->with('error', 'Beberapa kategori digunakan oleh produk dan tidak dapat dihapus.');
        }

        ProductCategory::whereIn('id', $request->ids)->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('catalog.product-categories.index') . ($currentQuery ? '?' . $currentQuery : '');

            return Redirect::to($redirectUrl)->with('success', 'Kategori produk berhasil dihapus.');
        }

        return Redirect::route('catalog.product-categories.index')->with('success', 'Kategori produk berhasil dihapus.');
    }

    public function exportXLSX(Request $request)
    {
        $categories = $this->getFilteredProductCategories($request);
        return Excel::download(new ProductCategoriesExport($categories), 'product-categories.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $categories = $this->getFilteredProductCategories($request);
        return Excel::download(new ProductCategoriesExport($categories), 'product-categories.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $categories = $this->getFilteredProductCategories($request);
        return Excel::download(new ProductCategoriesExport($categories), 'product-categories.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }

    private function validateRequest(Request $request, ?ProductCategory $productCategory = null): array
    {
        $companyId = $request->input('company_id');

        $parentRule = Rule::exists('product_categories', 'id');
        if ($companyId) {
            $parentRule = $parentRule->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            });
        }

        $parentRules = array_filter([
            'nullable',
            $parentRule,
            $productCategory ? Rule::notIn([$productCategory->id]) : null,
        ]);

        $rules = [
            'company_id' => ['required', 'exists:companies,id'],
            'attribute_set_id' => ['required', 'exists:attribute_sets,id'],
            'parent_id' => $parentRules,
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('product_categories', 'code')->ignore($productCategory?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
            'path' => ['nullable', 'string', 'max:500'],
            'create_another' => ['sometimes', 'boolean'],
        ];

        return $request->validate($rules);
    }

    private function buildCategoryPath(string $code, ?ProductCategory $parent = null): string
    {
        if ($parent) {
            $basePath = $parent->path ?: $parent->code;
            return trim($basePath . '/' . $code, '/');
        }

        return $code;
    }

    private function getFilteredProductCategories(Request $request)
    {
        $query = ProductCategory::with(['company', 'parent', 'attributeSet']);

        if ($request->filled('search')) {
            $search = strtolower($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where(DB::raw('lower(code)'), 'like', '%' . $search . '%')
                    ->orWhere(DB::raw('lower(name)'), 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->input('company_id'));
        }

        if ($request->filled('attribute_set_id')) {
            $query->where('attribute_set_id', $request->input('attribute_set_id'));
        }

        return $query->orderBy('sort_order')->get();
    }
}

