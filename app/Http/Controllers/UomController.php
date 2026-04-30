<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Uom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UomController extends Controller
{
    private const KINDS = ['each', 'weight', 'length', 'area', 'volume', 'time'];

    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('uoms.index_filters', []);
        Session::put('uoms.index_filters', $filters);

        $query = Uom::query();

        if (! empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where(DB::raw('lower(code)'), 'like', "%{$search}%")
                    ->orWhere(DB::raw('lower(name)'), 'like', "%{$search}%");
            });
        }

        if (! empty($filters['kind'])) {
            $kinds = is_array($filters['kind']) ? $filters['kind'] : [$filters['kind']];
            $query->whereIn('kind', $kinds);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'code';
        $sortOrder = $filters['order'] ?? 'asc';

        $allowedSorts = ['code', 'name', 'kind'];
        if (! in_array($sortColumn, $allowedSorts, true)) {
            $sortColumn = 'code';
        }
        if (! in_array(strtolower($sortOrder), ['asc', 'desc'], true)) {
            $sortOrder = 'asc';
        }

        $uoms = $query->orderBy($sortColumn, $sortOrder)->paginate($perPage)->onEachSide(0)->withQueryString();

        return Inertia::render('Uoms/Index', [
            'uoms' => $uoms,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
            'kinds' => $this->kindOptions(),
        ]);
    }

    public function create()
    {
        $filters = Session::get('uoms.index_filters', []);

        return Inertia::render('Uoms/Create', [
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'kinds' => $this->kindOptions(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);

        Uom::create([
            'company_id' => $validated['company_id'],
            'code' => mb_strtolower(trim($validated['code'])),
            'name' => $validated['name'],
            'kind' => $validated['kind'],
        ]);

        if ($request->boolean('create_another')) {
            return redirect()->route('uoms.create')->with('success', 'Satuan berhasil ditambahkan.');
        }

        return redirect()->route('uoms.index')->with('success', 'Satuan berhasil ditambahkan.');
    }

    public function edit(Uom $uom)
    {
        return Inertia::render('Uoms/Edit', [
            'uom' => $uom,
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'kinds' => $this->kindOptions(),
            'filters' => Session::get('uoms.index_filters', []),
        ]);
    }

    public function update(Request $request, Uom $uom)
    {
        $validated = $this->validateRequest($request, $uom);

        $uom->update([
            'company_id' => $validated['company_id'],
            'code' => mb_strtolower(trim($validated['code'])),
            'name' => $validated['name'],
            'kind' => $validated['kind'],
        ]);

        return redirect()->route('uoms.edit', $uom->id)->with('success', 'Satuan berhasil diubah.');
    }

    public function destroy(Request $request, Uom $uom)
    {
        if ($uom->productVariants()->exists() || $uom->defaultProducts()->exists()) {
            return redirect()->back()->with('error', 'Satuan sedang digunakan oleh produk dan tidak dapat dihapus.');
        }

        if ($uom->conversionsFrom()->exists() || $uom->conversionsTo()->exists()) {
            return redirect()->back()->with('error', 'Satuan masih memiliki konversi terkait. Hapus konversinya terlebih dulu.');
        }

        $uom->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('uoms.index').($currentQuery ? '?'.$currentQuery : '');

            return Redirect::to($redirectUrl)->with('success', 'Satuan berhasil dihapus.');
        }

        return redirect()->route('uoms.index')->with('success', 'Satuan berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:uoms,id'],
        ]);

        $inUse = Uom::whereIn('id', $request->ids)
            ->where(fn ($q) => $q->has('productVariants')->orHas('defaultProducts')->orHas('conversionsFrom')->orHas('conversionsTo'))
            ->exists();

        if ($inUse) {
            return redirect()->back()->with('error', 'Beberapa satuan masih digunakan dan tidak dapat dihapus.');
        }

        Uom::whereIn('id', $request->ids)->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('uoms.index').($currentQuery ? '?'.$currentQuery : '');

            return Redirect::to($redirectUrl)->with('success', 'Satuan berhasil dihapus.');
        }

        return redirect()->route('uoms.index')->with('success', 'Satuan berhasil dihapus.');
    }

    private function validateRequest(Request $request, ?Uom $uom = null): array
    {
        return $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('uoms', 'code')->ignore($uom?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in(self::KINDS)],
            'create_another' => ['sometimes', 'boolean'],
        ]);
    }

    private function kindOptions(): array
    {
        return collect(self::KINDS)->map(fn ($k) => [
            'value' => $k,
            'label' => match ($k) {
                'each' => 'Each (Satuan)',
                'weight' => 'Weight (Berat)',
                'length' => 'Length (Panjang)',
                'area' => 'Area (Luas)',
                'volume' => 'Volume',
                'time' => 'Time (Waktu)',
                default => $k,
            },
        ])->values()->toArray();
    }
}
