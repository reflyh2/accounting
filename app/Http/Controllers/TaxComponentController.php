<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\TaxComponent;
use App\Models\TaxJurisdiction;
use Illuminate\Http\Request;
use App\Exports\TaxComponentsExport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class TaxComponentController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('tax-components.index_filters', []);
        Session::put('tax-components.index_filters', $filters);

        $query = TaxComponent::with(['jurisdiction']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(code)'), 'like', '%' . strtolower($filters['search']) . '%');
            });
        }

        if (!empty($filters['kind'])) {
            $query->whereIn('kind', $filters['kind']);
        }

        if (!empty($filters['tax_jurisdiction_id'])) {
            $query->whereIn('tax_jurisdiction_id', $filters['tax_jurisdiction_id']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);

        $components = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $jurisdictions = TaxJurisdiction::orderBy('name', 'asc')->get();
        $kinds = self::getKinds();
        $cascadeModes = self::getCascadeModes();
        $deductibleModes = self::getDeductibleModes();

        return Inertia::render('TaxComponents/Index', [
            'components' => $components,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
            'jurisdictions' => $jurisdictions,
            'kinds' => $kinds,
            'cascadeModes' => $cascadeModes,
            'deductibleModes' => $deductibleModes,
        ]);
    }

    public function create()
    {
        $filters = Session::get('tax-components.index_filters', []);
        $jurisdictions = TaxJurisdiction::orderBy('name', 'asc')->get();

        return Inertia::render('TaxComponents/Create', [
            'filters' => $filters,
            'jurisdictions' => $jurisdictions,
            'kinds' => self::getKinds(),
            'cascadeModes' => self::getCascadeModes(),
            'deductibleModes' => self::getDeductibleModes(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tax_jurisdiction_id' => 'required|exists:tax_jurisdictions,id',
            'code' => 'required|string|max:50|unique:tax_components,code',
            'name' => 'required|string|max:255',
            'kind' => 'required|string|max:50',
            'cascade_mode' => 'required|string|max:50',
            'deductible_mode' => 'required|string|max:50',
        ]);

        $component = TaxComponent::create($validated);

        return redirect()->route('tax-components.show', $component->id)
            ->with('success', 'Komponen pajak berhasil dibuat.');
    }

    public function show(TaxComponent $taxComponent)
    {
        $filters = Session::get('tax-components.index_filters', []);
        $taxComponent->load(['jurisdiction', 'taxRules']);

        return Inertia::render('TaxComponents/Show', [
            'component' => $taxComponent,
            'filters' => $filters,
        ]);
    }

    public function edit(TaxComponent $taxComponent)
    {
        $filters = Session::get('tax-components.index_filters', []);
        $jurisdictions = TaxJurisdiction::orderBy('name', 'asc')->get();

        return Inertia::render('TaxComponents/Edit', [
            'component' => $taxComponent,
            'filters' => $filters,
            'jurisdictions' => $jurisdictions,
            'kinds' => self::getKinds(),
            'cascadeModes' => self::getCascadeModes(),
            'deductibleModes' => self::getDeductibleModes(),
        ]);
    }

    public function update(Request $request, TaxComponent $taxComponent)
    {
        $validated = $request->validate([
            'tax_jurisdiction_id' => 'required|exists:tax_jurisdictions,id',
            'code' => 'required|string|max:50|unique:tax_components,code,' . $taxComponent->id,
            'name' => 'required|string|max:255',
            'kind' => 'required|string|max:50',
            'cascade_mode' => 'required|string|max:50',
            'deductible_mode' => 'required|string|max:50',
        ]);

        $taxComponent->update($validated);

        return redirect()->route('tax-components.edit', $taxComponent->id)
            ->with('success', 'Komponen pajak berhasil diubah.');
    }

    public function destroy(Request $request, TaxComponent $taxComponent)
    {
        if ($taxComponent->taxRules()->exists()) {
            return redirect()->back()->with('error', 'Komponen pajak tidak dapat dihapus karena masih digunakan oleh aturan pajak.');
        }

        $taxComponent->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('tax-components.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Komponen pajak berhasil dihapus.');
        }

        return Redirect::route('tax-components.index')
            ->with('success', 'Komponen pajak berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        $errors = [];
        
        DB::transaction(function () use ($request, &$errors) {
            foreach ($request->ids as $id) {
                $component = TaxComponent::find($id);
                if ($component) {
                    if ($component->taxRules()->exists()) {
                        $errors[] = $component->name;
                    } else {
                        $component->delete();
                    }
                }
            }
        });

        if (!empty($errors)) {
            return redirect()->back()->with('warning', 'Beberapa komponen tidak dapat dihapus: ' . implode(', ', $errors));
        }

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('tax-components.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Komponen pajak berhasil dihapus.');
        }

        return Redirect::route('tax-components.index')
            ->with('success', 'Komponen pajak berhasil dihapus.');
    }

    private function getFilteredComponents(Request $request)
    {
        $filters = $request->all() ?: Session::get('tax-components.index_filters', []);

        $query = TaxComponent::with(['jurisdiction']);

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
        $components = $this->getFilteredComponents($request);
        return Excel::download(new TaxComponentsExport($components), 'tax-components.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $components = $this->getFilteredComponents($request);
        return Excel::download(new TaxComponentsExport($components), 'tax-components.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $components = $this->getFilteredComponents($request);
        return Excel::download(new TaxComponentsExport($components), 'tax-components.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }

    public static function getKinds(): array
    {
        return [
            'vat' => 'PPN (VAT)',
            'gst' => 'GST',
            'sales_tax' => 'Pajak Penjualan',
            'service_tax' => 'Pajak Jasa',
            'excise' => 'Cukai',
            'luxury' => 'Pajak Barang Mewah',
            'fee' => 'Biaya',
            'withholding' => 'PPh (Withholding)',
            'other' => 'Lainnya',
        ];
    }

    public static function getCascadeModes(): array
    {
        return [
            'parallel' => 'Paralel',
            'on_top_of_prev' => 'Kumulatif',
        ];
    }

    public static function getDeductibleModes(): array
    {
        return [
            'deductible' => 'Dapat Dikreditkan',
            'non_deductible' => 'Tidak Dapat Dikreditkan',
            'partial' => 'Sebagian',
        ];
    }
}
