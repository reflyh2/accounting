<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\TaxJurisdiction;
use Illuminate\Http\Request;
use App\Exports\TaxJurisdictionsExport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class TaxJurisdictionController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('tax-jurisdictions.index_filters', []);
        Session::put('tax-jurisdictions.index_filters', $filters);

        $query = TaxJurisdiction::with(['parent', 'children']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(code)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(country_code)'), 'like', '%' . strtolower($filters['search']) . '%');
            });
        }

        if (!empty($filters['level'])) {
            $query->whereIn('level', $filters['level']);
        }

        if (!empty($filters['country_code'])) {
            $query->whereIn('country_code', $filters['country_code']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);

        $jurisdictions = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $levels = TaxJurisdiction::distinct()->pluck('level')->filter()->values();
        $countryCodes = TaxJurisdiction::distinct()->pluck('country_code')->filter()->values();

        return Inertia::render('TaxJurisdictions/Index', [
            'jurisdictions' => $jurisdictions,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
            'levels' => $levels,
            'countryCodes' => $countryCodes,
        ]);
    }

    public function create()
    {
        $filters = Session::get('tax-jurisdictions.index_filters', []);
        $parentJurisdictions = TaxJurisdiction::orderBy('name', 'asc')->get();

        return Inertia::render('TaxJurisdictions/Create', [
            'filters' => $filters,
            'parentJurisdictions' => $parentJurisdictions,
            'levels' => self::getLevels(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:tax_jurisdictions,id',
            'code' => 'nullable|string|max:50|unique:tax_jurisdictions,code',
            'name' => 'required|string|max:255',
            'country_code' => 'required|string|size:2',
            'level' => 'required|string|max:50',
            'tax_authority' => 'nullable|string|max:255',
        ]);

        $jurisdiction = TaxJurisdiction::create($validated);

        return redirect()->route('tax-jurisdictions.show', $jurisdiction->id)
            ->with('success', 'Yurisdiksi pajak berhasil dibuat.');
    }

    public function show(TaxJurisdiction $taxJurisdiction)
    {
        $filters = Session::get('tax-jurisdictions.index_filters', []);
        $taxJurisdiction->load(['parent', 'children', 'components']);

        return Inertia::render('TaxJurisdictions/Show', [
            'jurisdiction' => $taxJurisdiction,
            'filters' => $filters,
        ]);
    }

    public function edit(TaxJurisdiction $taxJurisdiction)
    {
        $filters = Session::get('tax-jurisdictions.index_filters', []);
        $parentJurisdictions = TaxJurisdiction::where('id', '!=', $taxJurisdiction->id)
            ->orderBy('name', 'asc')
            ->get();

        return Inertia::render('TaxJurisdictions/Edit', [
            'jurisdiction' => $taxJurisdiction,
            'filters' => $filters,
            'parentJurisdictions' => $parentJurisdictions,
            'levels' => self::getLevels(),
        ]);
    }

    public function update(Request $request, TaxJurisdiction $taxJurisdiction)
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:tax_jurisdictions,id',
            'code' => 'nullable|string|max:50|unique:tax_jurisdictions,code,' . $taxJurisdiction->id,
            'name' => 'required|string|max:255',
            'country_code' => 'required|string|size:2',
            'level' => 'required|string|max:50',
            'tax_authority' => 'nullable|string|max:255',
        ]);

        $taxJurisdiction->update($validated);

        return redirect()->route('tax-jurisdictions.edit', $taxJurisdiction->id)
            ->with('success', 'Yurisdiksi pajak berhasil diubah.');
    }

    public function destroy(Request $request, TaxJurisdiction $taxJurisdiction)
    {
        if ($taxJurisdiction->components()->exists()) {
            return redirect()->back()->with('error', 'Yurisdiksi pajak tidak dapat dihapus karena masih memiliki komponen pajak.');
        }

        if ($taxJurisdiction->children()->exists()) {
            return redirect()->back()->with('error', 'Yurisdiksi pajak tidak dapat dihapus karena masih memiliki yurisdiksi anak.');
        }

        $taxJurisdiction->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('tax-jurisdictions.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Yurisdiksi pajak berhasil dihapus.');
        }

        return Redirect::route('tax-jurisdictions.index')
            ->with('success', 'Yurisdiksi pajak berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        $errors = [];
        
        DB::transaction(function () use ($request, &$errors) {
            foreach ($request->ids as $id) {
                $jurisdiction = TaxJurisdiction::find($id);
                if ($jurisdiction) {
                    if ($jurisdiction->components()->exists() || $jurisdiction->children()->exists()) {
                        $errors[] = $jurisdiction->name;
                    } else {
                        $jurisdiction->delete();
                    }
                }
            }
        });

        if (!empty($errors)) {
            return redirect()->back()->with('warning', 'Beberapa yurisdiksi tidak dapat dihapus: ' . implode(', ', $errors));
        }

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('tax-jurisdictions.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Yurisdiksi pajak berhasil dihapus.');
        }

        return Redirect::route('tax-jurisdictions.index')
            ->with('success', 'Yurisdiksi pajak berhasil dihapus.');
    }

    private function getFilteredJurisdictions(Request $request)
    {
        $filters = $request->all() ?: Session::get('tax-jurisdictions.index_filters', []);

        $query = TaxJurisdiction::with(['parent']);

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
        $jurisdictions = $this->getFilteredJurisdictions($request);
        return Excel::download(new TaxJurisdictionsExport($jurisdictions), 'tax-jurisdictions.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $jurisdictions = $this->getFilteredJurisdictions($request);
        return Excel::download(new TaxJurisdictionsExport($jurisdictions), 'tax-jurisdictions.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $jurisdictions = $this->getFilteredJurisdictions($request);
        return Excel::download(new TaxJurisdictionsExport($jurisdictions), 'tax-jurisdictions.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }

    public static function getLevels(): array
    {
        return [
            'country' => 'Negara',
            'state' => 'Negara Bagian',
            'province' => 'Provinsi',
            'county' => 'Kabupaten',
            'city' => 'Kota',
            'municipality' => 'Kotamadya',
            'district' => 'Kecamatan',
            'special_purpose_district' => 'Zona Khusus',
            'custom' => 'Kustom',
        ];
    }
}
