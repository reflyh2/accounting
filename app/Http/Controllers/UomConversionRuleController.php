<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Uom;
use App\Models\UomConversionRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UomConversionRuleController extends Controller
{
    private const METHODS = ['fixed_ratio', 'avg_weight', 'density'];

    private const CONTEXTS = ['purchase', 'sales', 'inventory', 'pricing'];

    private const ROUNDING_MODES = ['nearest', 'ceil', 'floor', 'truncate'];

    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('uom-conversion-rules.index_filters', []);
        Session::put('uom-conversion-rules.index_filters', $filters);

        $query = UomConversionRule::query()->with(['fromUom', 'toUom', 'product', 'variant', 'company', 'partner']);

        if (! empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereHas('fromUom', fn ($u) => $u->where(DB::raw('lower(code)'), 'like', "%{$search}%"))
                    ->orWhereHas('toUom', fn ($u) => $u->where(DB::raw('lower(code)'), 'like', "%{$search}%"))
                    ->orWhereHas('product', fn ($p) => $p->where(DB::raw('lower(name)'), 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['context'])) {
            $contexts = is_array($filters['context']) ? $filters['context'] : [$filters['context']];
            $query->whereIn('context', $contexts);
        }

        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (! empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        $perPage = $filters['per_page'] ?? 10;

        $rules = $query->orderByDesc('id')->paginate($perPage)->onEachSide(0)->withQueryString();

        return Inertia::render('UomConversionRules/Index', [
            'rules' => $rules,
            'filters' => $filters,
            'perPage' => $perPage,
            'contexts' => $this->contextOptions(),
        ]);
    }

    public function create()
    {
        return Inertia::render('UomConversionRules/Create', [
            'uoms' => Uom::orderBy('code')->get(['id', 'code', 'name', 'kind']),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'methods' => $this->methodOptions(),
            'contexts' => $this->contextOptions(),
            'roundingModes' => $this->roundingModeOptions(),
            'filters' => Session::get('uom-conversion-rules.index_filters', []),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);
        $rule = UomConversionRule::create($this->normalizeAttributes($validated));

        if ($request->boolean('create_another')) {
            return redirect()->route('uom-conversion-rules.create')->with('success', 'Aturan konversi berhasil ditambahkan.');
        }

        return redirect()->route('uom-conversion-rules.index')->with('success', 'Aturan konversi berhasil ditambahkan.');
    }

    public function edit(UomConversionRule $uomConversionRule)
    {
        return Inertia::render('UomConversionRules/Edit', [
            'rule' => $uomConversionRule->load(['fromUom', 'toUom', 'product.variants', 'variant', 'company', 'partner']),
            'uoms' => Uom::orderBy('code')->get(['id', 'code', 'name', 'kind']),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'methods' => $this->methodOptions(),
            'contexts' => $this->contextOptions(),
            'roundingModes' => $this->roundingModeOptions(),
            'filters' => Session::get('uom-conversion-rules.index_filters', []),
        ]);
    }

    public function update(Request $request, UomConversionRule $uomConversionRule)
    {
        $validated = $this->validateRequest($request, $uomConversionRule);
        $uomConversionRule->update($this->normalizeAttributes($validated));

        return redirect()->route('uom-conversion-rules.edit', $uomConversionRule->id)
            ->with('success', 'Aturan konversi berhasil diubah.');
    }

    public function destroy(Request $request, UomConversionRule $uomConversionRule)
    {
        $uomConversionRule->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('uom-conversion-rules.index').($currentQuery ? '?'.$currentQuery : '');

            return Redirect::to($redirectUrl)->with('success', 'Aturan konversi berhasil dihapus.');
        }

        return redirect()->route('uom-conversion-rules.index')->with('success', 'Aturan konversi berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:uom_conversion_rules,id'],
        ]);

        UomConversionRule::whereIn('id', $request->ids)->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('uom-conversion-rules.index').($currentQuery ? '?'.$currentQuery : '');

            return Redirect::to($redirectUrl)->with('success', 'Aturan konversi berhasil dihapus.');
        }

        return redirect()->route('uom-conversion-rules.index')->with('success', 'Aturan konversi berhasil dihapus.');
    }

    private function validateRequest(Request $request, ?UomConversionRule $rule = null): array
    {
        $validated = $request->validate([
            'from_uom_id' => ['required', 'exists:uoms,id', 'different:to_uom_id'],
            'to_uom_id' => ['required', 'exists:uoms,id'],
            'method' => ['required', Rule::in(self::METHODS)],
            'numerator' => ['nullable', 'numeric', 'gt:0'],
            'denominator' => ['nullable', 'numeric', 'gt:0'],
            'avg_weight_g' => ['nullable', 'numeric', 'gt:0'],
            'density_kg_per_l' => ['nullable', 'numeric', 'gt:0'],
            'product_id' => ['nullable', 'exists:products,id'],
            'variant_id' => ['nullable', 'exists:product_variants,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'partner_id' => ['nullable', 'exists:partners,id'],
            'context' => ['nullable', Rule::in(self::CONTEXTS)],
            'rounding_mode' => ['required', Rule::in(self::ROUNDING_MODES)],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:9'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'notes' => ['nullable', 'string'],
            'create_another' => ['sometimes', 'boolean'],
        ]);

        // Method-specific requirements
        if ($validated['method'] === 'fixed_ratio') {
            if (empty($validated['numerator']) || empty($validated['denominator'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'numerator' => 'Pembilang dan penyebut wajib diisi untuk metode rasio tetap.',
                ]);
            }
        } elseif ($validated['method'] === 'avg_weight') {
            if (empty($validated['avg_weight_g'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'avg_weight_g' => 'Berat rata-rata (gram) wajib diisi untuk metode avg_weight.',
                ]);
            }
        } elseif ($validated['method'] === 'density') {
            if (empty($validated['density_kg_per_l'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'density_kg_per_l' => 'Densitas (kg/L) wajib diisi untuk metode density.',
                ]);
            }
        }

        return $validated;
    }

    /**
     * Compute factor from method-specific inputs and clear unused method fields.
     */
    private function normalizeAttributes(array $validated): array
    {
        $attrs = $validated;
        unset($attrs['create_another']);

        switch ($validated['method']) {
            case 'fixed_ratio':
                $attrs['factor'] = (float) $validated['numerator'] / (float) $validated['denominator'];
                $attrs['avg_weight_g'] = null;
                $attrs['density_kg_per_l'] = null;
                break;
            case 'avg_weight':
                // 1 unit on 'each' side ≈ avg_weight_g grams. Factor in canonical
                // direction (each → kg) is avg_weight_g / 1000. Stored for reference;
                // actual conversion engine is responsible for direction-aware math.
                $attrs['factor'] = (float) $validated['avg_weight_g'] / 1000;
                $attrs['numerator'] = null;
                $attrs['denominator'] = null;
                break;
            case 'density':
                // density kg per L. Factor (L → kg) = density.
                $attrs['factor'] = (float) $validated['density_kg_per_l'];
                $attrs['numerator'] = null;
                $attrs['denominator'] = null;
                break;
        }

        return $attrs;
    }

    private function methodOptions(): array
    {
        return [
            ['value' => 'fixed_ratio', 'label' => 'Rasio Tetap (mis. 1 lusin = 12 pcs)'],
            ['value' => 'avg_weight', 'label' => 'Berat Rata-rata (gram per unit)'],
            ['value' => 'density', 'label' => 'Densitas (kg per liter)'],
        ];
    }

    private function contextOptions(): array
    {
        return [
            ['value' => 'purchase', 'label' => 'Pembelian'],
            ['value' => 'sales', 'label' => 'Penjualan'],
            ['value' => 'inventory', 'label' => 'Inventaris'],
            ['value' => 'pricing', 'label' => 'Harga'],
        ];
    }

    private function roundingModeOptions(): array
    {
        return [
            ['value' => 'nearest', 'label' => 'Pembulatan Terdekat'],
            ['value' => 'ceil', 'label' => 'Pembulatan Ke Atas'],
            ['value' => 'floor', 'label' => 'Pembulatan Ke Bawah'],
            ['value' => 'truncate', 'label' => 'Potong Desimal'],
        ];
    }
}
