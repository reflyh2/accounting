<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\TaxCategory;
use App\Models\Company;
use Illuminate\Http\Request;
use App\Exports\TaxCategoriesExport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class TaxCategoryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('tax-categories.index_filters', []);
        Session::put('tax-categories.index_filters', $filters);

        $query = TaxCategory::with(['company']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(code)'), 'like', '%' . strtolower($filters['search']) . '%');
            });
        }

        if (!empty($filters['applies_to'])) {
            $query->whereIn('applies_to', $filters['applies_to']);
        }

        if (!empty($filters['default_behavior'])) {
            $query->whereIn('default_behavior', $filters['default_behavior']);
        }

        if (!empty($filters['company_id'])) {
            $query->whereIn('company_id', $filters['company_id']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);

        $categories = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();

        return Inertia::render('TaxCategories/Index', [
            'categories' => $categories,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
            'companies' => $companies,
            'appliesTo' => self::getAppliesTo(),
            'behaviors' => self::getBehaviors(),
        ]);
    }

    public function create()
    {
        $filters = Session::get('tax-categories.index_filters', []);
        $companies = Company::orderBy('name', 'asc')->get();

        return Inertia::render('TaxCategories/Create', [
            'filters' => $filters,
            'companies' => $companies,
            'appliesTo' => self::getAppliesTo(),
            'behaviors' => self::getBehaviors(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'code' => 'required|string|max:50|unique:tax_categories,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'applies_to' => 'required|string|max:50',
            'default_behavior' => 'required|string|max:50',
        ]);

        $category = TaxCategory::create($validated);

        return redirect()->route('tax-categories.show', $category->id)
            ->with('success', 'Kategori pajak berhasil dibuat.');
    }

    public function show(TaxCategory $taxCategory)
    {
        $filters = Session::get('tax-categories.index_filters', []);
        $taxCategory->load(['company', 'taxRules', 'products']);

        return Inertia::render('TaxCategories/Show', [
            'category' => $taxCategory,
            'filters' => $filters,
        ]);
    }

    public function edit(TaxCategory $taxCategory)
    {
        $filters = Session::get('tax-categories.index_filters', []);
        $companies = Company::orderBy('name', 'asc')->get();

        return Inertia::render('TaxCategories/Edit', [
            'category' => $taxCategory,
            'filters' => $filters,
            'companies' => $companies,
            'appliesTo' => self::getAppliesTo(),
            'behaviors' => self::getBehaviors(),
        ]);
    }

    public function update(Request $request, TaxCategory $taxCategory)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'code' => 'required|string|max:50|unique:tax_categories,code,' . $taxCategory->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'applies_to' => 'required|string|max:50',
            'default_behavior' => 'required|string|max:50',
        ]);

        $taxCategory->update($validated);

        return redirect()->route('tax-categories.edit', $taxCategory->id)
            ->with('success', 'Kategori pajak berhasil diubah.');
    }

    public function destroy(Request $request, TaxCategory $taxCategory)
    {
        if ($taxCategory->taxRules()->exists()) {
            return redirect()->back()->with('error', 'Kategori pajak tidak dapat dihapus karena masih digunakan oleh aturan pajak.');
        }

        if ($taxCategory->products()->exists()) {
            return redirect()->back()->with('error', 'Kategori pajak tidak dapat dihapus karena masih digunakan oleh produk.');
        }

        $taxCategory->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('tax-categories.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Kategori pajak berhasil dihapus.');
        }

        return Redirect::route('tax-categories.index')
            ->with('success', 'Kategori pajak berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        $errors = [];
        
        DB::transaction(function () use ($request, &$errors) {
            foreach ($request->ids as $id) {
                $category = TaxCategory::find($id);
                if ($category) {
                    if ($category->taxRules()->exists() || $category->products()->exists()) {
                        $errors[] = $category->name;
                    } else {
                        $category->delete();
                    }
                }
            }
        });

        if (!empty($errors)) {
            return redirect()->back()->with('warning', 'Beberapa kategori tidak dapat dihapus: ' . implode(', ', $errors));
        }

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('tax-categories.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Kategori pajak berhasil dihapus.');
        }

        return Redirect::route('tax-categories.index')
            ->with('success', 'Kategori pajak berhasil dihapus.');
    }

    private function getFilteredCategories(Request $request)
    {
        $filters = $request->all() ?: Session::get('tax-categories.index_filters', []);

        $query = TaxCategory::with(['company']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(code)'), 'like', '%' . strtolower($filters['search']) . '%');
            });
        }

        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $categories = $this->getFilteredCategories($request);
        return Excel::download(new TaxCategoriesExport($categories), 'tax-categories.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $categories = $this->getFilteredCategories($request);
        return Excel::download(new TaxCategoriesExport($categories), 'tax-categories.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $categories = $this->getFilteredCategories($request);
        return Excel::download(new TaxCategoriesExport($categories), 'tax-categories.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }

    public static function getAppliesTo(): array
    {
        return [
            'goods' => 'Barang',
            'services' => 'Jasa',
            'both' => 'Keduanya',
        ];
    }

    public static function getBehaviors(): array
    {
        return [
            'taxable' => 'Kena Pajak',
            'zero_rated' => 'Tarif Nol',
            'exempt' => 'Bebas Pajak',
            'out_of_scope' => 'Di Luar Lingkup',
            'reverse_charge_candidate' => 'Kandidat Pembalikan Beban',
        ];
    }
}
