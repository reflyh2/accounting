<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\TaxRule;
use App\Models\TaxCategory;
use App\Models\TaxJurisdiction;
use App\Models\TaxComponent;
use App\Models\Uom;
use Illuminate\Http\Request;
use App\Exports\TaxRulesExport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class TaxRuleController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('tax-rules.index_filters', []);
        Session::put('tax-rules.index_filters', $filters);

        $query = TaxRule::with(['taxCategory', 'jurisdiction', 'component']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereHas('taxCategory', function ($q) use ($filters) {
                    $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                })
                ->orWhereHas('component', function ($q) use ($filters) {
                    $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                });
            });
        }

        if (!empty($filters['tax_category_id'])) {
            $query->whereIn('tax_category_id', $filters['tax_category_id']);
        }

        if (!empty($filters['tax_jurisdiction_id'])) {
            $query->whereIn('tax_jurisdiction_id', $filters['tax_jurisdiction_id']);
        }

        if (!empty($filters['tax_component_id'])) {
            $query->whereIn('tax_component_id', $filters['tax_component_id']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'priority';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);

        $rules = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $categories = TaxCategory::orderBy('name', 'asc')->get();
        $jurisdictions = TaxJurisdiction::orderBy('name', 'asc')->get();
        $components = TaxComponent::orderBy('name', 'asc')->get();

        return Inertia::render('TaxRules/Index', [
            'rules' => $rules,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
            'categories' => $categories,
            'jurisdictions' => $jurisdictions,
            'components' => $components,
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('tax-rules.index_filters', []);
        $categories = TaxCategory::orderBy('name', 'asc')->get();
        $jurisdictions = TaxJurisdiction::orderBy('name', 'asc')->get();
        $components = TaxComponent::orderBy('name', 'asc')->get();
        $uoms = Uom::orderBy('name', 'asc')->get();

        return Inertia::render('TaxRules/Create', [
            'filters' => $filters,
            'categories' => $categories,
            'jurisdictions' => $jurisdictions,
            'components' => fn() => $request->tax_jurisdiction_id 
                ? TaxComponent::where('tax_jurisdiction_id', $request->tax_jurisdiction_id)->orderBy('name', 'asc')->get() 
                : $components,
            'uoms' => $uoms,
            'rateTypes' => self::getRateTypes(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tax_category_id' => 'required|exists:tax_categories,id',
            'tax_jurisdiction_id' => 'required|exists:tax_jurisdictions,id',
            'tax_component_id' => 'required|exists:tax_components,id',
            'rate_type' => 'required|string|max:50',
            'rate_value' => 'required|numeric|min:0',
            'per_unit_uom_id' => 'nullable|exists:uoms,id',
            'tax_inclusive' => 'boolean',
            'b2b_applicable' => 'nullable|boolean',
            'reverse_charge' => 'boolean',
            'export_zero_rate' => 'boolean',
            'threshold_amount' => 'nullable|numeric|min:0',
            'priority' => 'integer|min:0',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);

        $validated['created_by'] = $request->user()->global_id;

        $rule = TaxRule::create($validated);

        return redirect()->route('tax-rules.show', $rule->id)
            ->with('success', 'Aturan pajak berhasil dibuat.');
    }

    public function show(TaxRule $taxRule)
    {
        $filters = Session::get('tax-rules.index_filters', []);
        $taxRule->load(['taxCategory.company', 'jurisdiction', 'component', 'perUnitUom', 'creator', 'updater']);

        return Inertia::render('TaxRules/Show', [
            'rule' => $taxRule,
            'filters' => $filters,
            'rateTypes' => self::getRateTypes(),
        ]);
    }

    public function edit(Request $request, TaxRule $taxRule)
    {
        $filters = Session::get('tax-rules.index_filters', []);
        $taxRule->load(['taxCategory', 'jurisdiction', 'component']);
        
        $categories = TaxCategory::orderBy('name', 'asc')->get();
        $jurisdictions = TaxJurisdiction::orderBy('name', 'asc')->get();
        $components = TaxComponent::where('tax_jurisdiction_id', $taxRule->tax_jurisdiction_id)
            ->orderBy('name', 'asc')
            ->get();
        $uoms = Uom::orderBy('name', 'asc')->get();

        return Inertia::render('TaxRules/Edit', [
            'rule' => $taxRule,
            'filters' => $filters,
            'categories' => $categories,
            'jurisdictions' => $jurisdictions,
            'components' => $components,
            'uoms' => $uoms,
            'rateTypes' => self::getRateTypes(),
        ]);
    }

    public function update(Request $request, TaxRule $taxRule)
    {
        $validated = $request->validate([
            'tax_category_id' => 'required|exists:tax_categories,id',
            'tax_jurisdiction_id' => 'required|exists:tax_jurisdictions,id',
            'tax_component_id' => 'required|exists:tax_components,id',
            'rate_type' => 'required|string|max:50',
            'rate_value' => 'required|numeric|min:0',
            'per_unit_uom_id' => 'nullable|exists:uoms,id',
            'tax_inclusive' => 'boolean',
            'b2b_applicable' => 'nullable|boolean',
            'reverse_charge' => 'boolean',
            'export_zero_rate' => 'boolean',
            'threshold_amount' => 'nullable|numeric|min:0',
            'priority' => 'integer|min:0',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);

        $validated['updated_by'] = $request->user()->global_id;

        $taxRule->update($validated);

        return redirect()->route('tax-rules.edit', $taxRule->id)
            ->with('success', 'Aturan pajak berhasil diubah.');
    }

    public function destroy(Request $request, TaxRule $taxRule)
    {
        $taxRule->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('tax-rules.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Aturan pajak berhasil dihapus.');
        }

        return Redirect::route('tax-rules.index')
            ->with('success', 'Aturan pajak berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        DB::transaction(function () use ($request) {
            foreach ($request->ids as $id) {
                $rule = TaxRule::find($id);
                if ($rule) {
                    $rule->delete();
                }
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('tax-rules.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Aturan pajak berhasil dihapus.');
        }

        return Redirect::route('tax-rules.index')
            ->with('success', 'Aturan pajak berhasil dihapus.');
    }

    private function getFilteredRules(Request $request)
    {
        $filters = $request->all() ?: Session::get('tax-rules.index_filters', []);

        $query = TaxRule::with(['taxCategory', 'jurisdiction', 'component']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereHas('taxCategory', function ($q) use ($filters) {
                    $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                });
            });
        }

        $sortColumn = $filters['sort'] ?? 'priority';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $rules = $this->getFilteredRules($request);
        return Excel::download(new TaxRulesExport($rules), 'tax-rules.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $rules = $this->getFilteredRules($request);
        return Excel::download(new TaxRulesExport($rules), 'tax-rules.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $rules = $this->getFilteredRules($request);
        return Excel::download(new TaxRulesExport($rules), 'tax-rules.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }

    public static function getRateTypes(): array
    {
        return [
            'percent' => 'Persentase',
            'fixed_per_unit' => 'Tetap Per Unit',
        ];
    }
}
