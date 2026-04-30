<?php

namespace App\Http\Controllers;

use App\Models\Uom;
use App\Models\UomConversion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

class UomConversionController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('uom-conversions.index_filters', []);
        Session::put('uom-conversions.index_filters', $filters);

        $query = UomConversion::query()->with(['fromUom', 'toUom']);

        if (! empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereHas('fromUom', fn ($u) => $u->where(DB::raw('lower(code)'), 'like', "%{$search}%")
                    ->orWhere(DB::raw('lower(name)'), 'like', "%{$search}%"))
                    ->orWhereHas('toUom', fn ($u) => $u->where(DB::raw('lower(code)'), 'like', "%{$search}%")
                        ->orWhere(DB::raw('lower(name)'), 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['kind'])) {
            $kinds = is_array($filters['kind']) ? $filters['kind'] : [$filters['kind']];
            $query->whereHas('fromUom', fn ($u) => $u->whereIn('kind', $kinds));
        }

        $perPage = $filters['per_page'] ?? 10;

        $conversions = $query->orderBy('id')->paginate($perPage)->onEachSide(0)->withQueryString();

        return Inertia::render('UomConversions/Index', [
            'conversions' => $conversions,
            'filters' => $filters,
            'perPage' => $perPage,
            'kinds' => $this->kindOptions(),
        ]);
    }

    public function create()
    {
        return Inertia::render('UomConversions/Create', [
            'uoms' => Uom::orderBy('code')->get(['id', 'code', 'name', 'kind']),
            'filters' => Session::get('uom-conversions.index_filters', []),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);

        DB::transaction(function () use ($validated, $request) {
            $factor = $validated['numerator'] / $validated['denominator'];

            UomConversion::updateOrCreate(
                ['from_uom_id' => $validated['from_uom_id'], 'to_uom_id' => $validated['to_uom_id']],
                ['numerator' => $validated['numerator'], 'denominator' => $validated['denominator'], 'factor' => $factor],
            );

            // Auto-create reverse conversion (from_uom <-> to_uom flipped, numerator/denominator swapped)
            if ($request->boolean('create_reverse', true)) {
                UomConversion::updateOrCreate(
                    ['from_uom_id' => $validated['to_uom_id'], 'to_uom_id' => $validated['from_uom_id']],
                    [
                        'numerator' => $validated['denominator'],
                        'denominator' => $validated['numerator'],
                        'factor' => $validated['denominator'] / $validated['numerator'],
                    ],
                );
            }
        });

        if ($request->boolean('create_another')) {
            return redirect()->route('uom-conversions.create')->with('success', 'Konversi satuan berhasil ditambahkan.');
        }

        return redirect()->route('uom-conversions.index')->with('success', 'Konversi satuan berhasil ditambahkan.');
    }

    public function edit(UomConversion $uomConversion)
    {
        return Inertia::render('UomConversions/Edit', [
            'conversion' => $uomConversion->load(['fromUom', 'toUom']),
            'uoms' => Uom::orderBy('code')->get(['id', 'code', 'name', 'kind']),
            'filters' => Session::get('uom-conversions.index_filters', []),
        ]);
    }

    public function update(Request $request, UomConversion $uomConversion)
    {
        $validated = $this->validateRequest($request, $uomConversion);

        DB::transaction(function () use ($uomConversion, $validated, $request) {
            $factor = $validated['numerator'] / $validated['denominator'];

            $uomConversion->update([
                'from_uom_id' => $validated['from_uom_id'],
                'to_uom_id' => $validated['to_uom_id'],
                'numerator' => $validated['numerator'],
                'denominator' => $validated['denominator'],
                'factor' => $factor,
            ]);

            if ($request->boolean('create_reverse', false)) {
                UomConversion::updateOrCreate(
                    ['from_uom_id' => $validated['to_uom_id'], 'to_uom_id' => $validated['from_uom_id']],
                    [
                        'numerator' => $validated['denominator'],
                        'denominator' => $validated['numerator'],
                        'factor' => $validated['denominator'] / $validated['numerator'],
                    ],
                );
            }
        });

        return redirect()->route('uom-conversions.edit', $uomConversion->id)->with('success', 'Konversi satuan berhasil diubah.');
    }

    public function destroy(Request $request, UomConversion $uomConversion)
    {
        $uomConversion->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('uom-conversions.index').($currentQuery ? '?'.$currentQuery : '');

            return Redirect::to($redirectUrl)->with('success', 'Konversi satuan berhasil dihapus.');
        }

        return redirect()->route('uom-conversions.index')->with('success', 'Konversi satuan berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:uom_conversions,id'],
        ]);

        UomConversion::whereIn('id', $request->ids)->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('uom-conversions.index').($currentQuery ? '?'.$currentQuery : '');

            return Redirect::to($redirectUrl)->with('success', 'Konversi satuan berhasil dihapus.');
        }

        return redirect()->route('uom-conversions.index')->with('success', 'Konversi satuan berhasil dihapus.');
    }

    private function validateRequest(Request $request, ?UomConversion $current = null): array
    {
        $validated = $request->validate([
            'from_uom_id' => ['required', 'exists:uoms,id', 'different:to_uom_id'],
            'to_uom_id' => ['required', 'exists:uoms,id'],
            'numerator' => ['required', 'numeric', 'gt:0'],
            'denominator' => ['required', 'numeric', 'gt:0'],
            'create_reverse' => ['sometimes', 'boolean'],
            'create_another' => ['sometimes', 'boolean'],
        ]);

        // Same kind enforcement
        $fromUom = Uom::find($validated['from_uom_id']);
        $toUom = Uom::find($validated['to_uom_id']);
        if ($fromUom && $toUom && $fromUom->kind !== $toUom->kind) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'to_uom_id' => 'Satuan tujuan harus memiliki jenis yang sama dengan satuan asal.',
            ]);
        }

        // Prevent duplicate (from, to) pair (other than the current row when editing)
        $existsQuery = UomConversion::where('from_uom_id', $validated['from_uom_id'])
            ->where('to_uom_id', $validated['to_uom_id']);
        if ($current) {
            $existsQuery->where('id', '!=', $current->id);
        }
        if ($existsQuery->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'to_uom_id' => 'Konversi untuk pasangan satuan ini sudah ada.',
            ]);
        }

        return $validated;
    }

    private function kindOptions(): array
    {
        return [
            ['value' => 'each', 'label' => 'Each (Satuan)'],
            ['value' => 'weight', 'label' => 'Weight (Berat)'],
            ['value' => 'length', 'label' => 'Length (Panjang)'],
            ['value' => 'area', 'label' => 'Area (Luas)'],
            ['value' => 'volume', 'label' => 'Volume'],
            ['value' => 'time', 'label' => 'Time (Waktu)'],
        ];
    }
}
