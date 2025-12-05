<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Company;
use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialLine;
use Illuminate\Http\Request;
use App\Exports\BillOfMaterialsExport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class BillOfMaterialController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('bill_of_materials.index_filters', []);

        Session::put('bill_of_materials.index_filters', $filters);

        $query = BillOfMaterial::with(['branch.branchGroup.company', 'finishedProduct', 'finishedUom'])
            ->withCount('bomLines');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(bom_number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(description)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('finishedProduct', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  })
                  ->orWhereHas('branch', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  });
            });
        }

        if (!empty($filters['company_id'])) {
            $query->whereIn('company_id', $filters['company_id']);
        }

        if (!empty($filters['branch_id'])) {
            $query->whereIn('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        if (!empty($filters['finished_product_id'])) {
            $query->where('finished_product_id', $filters['finished_product_id']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'created_at';
        $sortOrder = $filters['order'] ?? 'desc';

        $query->orderBy($sortColumn, $sortOrder);

        $boms = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();

        if (!empty($filters['company_id'])) {
            $branches = Branch::whereIn('branch_group.company_id', $filters['company_id'])
                ->join('branch_groups', 'branches.branch_group_id', '=', 'branch_groups.id')
                ->select('branches.*')
                ->get();
        } else {
            $branches = Branch::orderBy('name', 'asc')->get();
        }

        $finishedProducts = Product::where('kind', 'goods')->orderBy('name', 'asc')->get();

        return Inertia::render('BillOfMaterials/Index', [
            'boms' => $boms,
            'companies' => $companies,
            'branches' => $branches,
            'finishedProducts' => $finishedProducts,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('bill_of_materials.index_filters', []);

        return Inertia::render('BillOfMaterials/Create', [
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => fn() => Branch::whereHas('branchGroup', function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            })->orderBy('name', 'asc')->get(),
            'finishedProducts' => fn() => Product::whereHas('companies', function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            })->where('kind', 'goods')->with('defaultUom', 'variants')->orderBy('name', 'asc')->get(),
            'componentProducts' => fn() => Product::whereHas('companies', function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            })->where('kind', 'goods')->with('defaultUom', 'variants')->orderBy('name', 'asc')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'branch_id' => 'required|exists:branches,id',
            'finished_product_id' => 'required|exists:products,id',
            'finished_product_variant_id' => 'nullable|exists:product_variants,id',
            'finished_quantity' => 'required|numeric|min:0.001',
            'finished_uom_id' => 'required|exists:uoms,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'version' => 'nullable|string|max:20',
            'effective_date' => 'nullable|date',
            'expiration_date' => 'nullable|date|after:effective_date',
            'status' => 'required|in:draft,active,inactive',
            'is_default' => 'boolean',
            'lines' => 'required|array|min:1',
            'lines.*.component_product_id' => 'required|exists:products,id',
            'lines.*.component_product_variant_id' => 'nullable|exists:product_variants,id',
            'lines.*.quantity_per' => 'required|numeric|min:0.001',
            'lines.*.uom_id' => 'required|exists:uoms,id',
            'lines.*.scrap_percentage' => 'numeric|min:0|max:100',
            'lines.*.backflush' => 'boolean',
            'lines.*.operation' => 'nullable|string|max:255',
            'lines.*.notes' => 'nullable|string',
        ]);

        $bom = DB::transaction(function () use ($validated, $request) {
            $bom = BillOfMaterial::create([
                'company_id' => $validated['company_id'],
                'branch_id' => $validated['branch_id'],
                'user_global_id' => $request->user()->global_id,
                'finished_product_id' => $validated['finished_product_id'],
                'finished_product_variant_id' => $validated['finished_product_variant_id'],
                'finished_quantity' => $validated['finished_quantity'],
                'finished_uom_id' => $validated['finished_uom_id'],
                'name' => $validated['name'],
                'description' => $validated['description'],
                'version' => $validated['version'] ?? '1.0',
                'effective_date' => $validated['effective_date'],
                'expiration_date' => $validated['expiration_date'],
                'status' => $validated['status'],
                'is_default' => $validated['is_default'] ?? false,
            ]);

            foreach ($validated['lines'] as $index => $line) {
                $bom->bomLines()->create([
                    'line_number' => $index + 1,
                    'component_product_id' => $line['component_product_id'],
                    'component_product_variant_id' => $line['component_product_variant_id'],
                    'quantity_per' => $line['quantity_per'],
                    'uom_id' => $line['uom_id'],
                    'scrap_percentage' => $line['scrap_percentage'] ?? 0,
                    'backflush' => $line['backflush'] ?? false,
                    'operation' => $line['operation'],
                    'notes' => $line['notes'],
                ]);
            }

            return $bom;
        });

        return redirect()->route('bill-of-materials.show', $bom->id)
            ->with('success', 'BOM berhasil dibuat.');
    }

    public function show(Request $request, $id)
    {
        $filters = Session::get('bill_of_materials.index_filters', []);
        $bom = BillOfMaterial::find($id);
        $bom->load(['branch.branchGroup.company', 'finishedProduct', 'finishedProductVariant', 'finishedUom', 'bomLines.componentProduct', 'bomLines.componentProductVariant', 'bomLines.uom', 'user']);

        return Inertia::render('BillOfMaterials/Show', [
            'bom' => $bom,
            'filters' => $filters,
        ]);
    }

    public function edit(Request $request, BillOfMaterial $bom)
    {
        $filters = Session::get('bill_of_materials.index_filters', []);
        $bom->load(['branch.branchGroup', 'finishedProduct', 'finishedProductVariant', 'finishedUom', 'bomLines.componentProduct', 'bomLines.componentProductVariant', 'bomLines.uom']);

        return Inertia::render('BillOfMaterials/Edit', [
            'bom' => $bom,
            'filters' => $filters,
            'companies' => Company::orderBy('name', 'asc')->get(),
            'branches' => Branch::whereHas('branchGroup', function ($query) use ($bom) {
                $query->where('company_id', $bom->company_id);
            })->orderBy('name', 'asc')->get(),
            'finishedProducts' => Product::whereHas('companies', function ($query) use ($bom) {
                $query->where('company_id', $bom->company_id);
            })->where('kind', 'goods')->with('defaultUom')->orderBy('name', 'asc')->get(),
            'componentProducts' => Product::whereHas('companies', function ($query) use ($bom) {
                $query->where('company_id', $bom->company_id);
            })->where('kind', 'goods')->with('defaultUom')->orderBy('name', 'asc')->get(),
        ]);
    }

    public function update(Request $request, BillOfMaterial $bom)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'branch_id' => 'required|exists:branches,id',
            'finished_product_id' => 'required|exists:products,id',
            'finished_product_variant_id' => 'nullable|exists:product_variants,id',
            'finished_quantity' => 'required|numeric|min:0.001',
            'finished_uom_id' => 'required|exists:uoms,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'version' => 'nullable|string|max:20',
            'effective_date' => 'nullable|date',
            'expiration_date' => 'nullable|date|after:effective_date',
            'status' => 'required|in:draft,active,inactive',
            'is_default' => 'boolean',
            'lines' => 'required|array|min:1',
            'lines.*.id' => 'nullable|exists:bill_of_material_lines,id',
            'lines.*.component_product_id' => 'required|exists:products,id',
            'lines.*.component_product_variant_id' => 'nullable|exists:product_variants,id',
            'lines.*.quantity_per' => 'required|numeric|min:0.001',
            'lines.*.uom_id' => 'required|exists:uoms,id',
            'lines.*.scrap_percentage' => 'numeric|min:0|max:100',
            'lines.*.backflush' => 'boolean',
            'lines.*.operation' => 'nullable|string|max:255',
            'lines.*.notes' => 'nullable|string',
        ]);

        // Check if company has changed
        if ($bom->company_id !== $validated['company_id']) {
            return redirect()->back()->with('error', 'Perusahaan BOM tidak dapat diubah.');
        }

        DB::transaction(function () use ($validated, $bom) {
            $bom->update([
                'branch_id' => $validated['branch_id'],
                'finished_product_id' => $validated['finished_product_id'],
                'finished_product_variant_id' => $validated['finished_product_variant_id'],
                'finished_quantity' => $validated['finished_quantity'],
                'finished_uom_id' => $validated['finished_uom_id'],
                'name' => $validated['name'],
                'description' => $validated['description'],
                'version' => $validated['version'] ?? $bom->version,
                'effective_date' => $validated['effective_date'],
                'expiration_date' => $validated['expiration_date'],
                'status' => $validated['status'],
                'is_default' => $validated['is_default'] ?? false,
            ]);

            // Delete existing lines
            $bom->bomLines()->delete();

            // Create new lines
            foreach ($validated['lines'] as $index => $line) {
                $bom->bomLines()->create([
                    'line_number' => $index + 1,
                    'component_product_id' => $line['component_product_id'],
                    'component_product_variant_id' => $line['component_product_variant_id'],
                    'quantity_per' => $line['quantity_per'],
                    'uom_id' => $line['uom_id'],
                    'scrap_percentage' => $line['scrap_percentage'] ?? 0,
                    'backflush' => $line['backflush'] ?? false,
                    'operation' => $line['operation'],
                    'notes' => $line['notes'],
                ]);
            }
        });

        return redirect()->route('bill-of-materials.edit', $bom->id)
            ->with('success', 'BOM berhasil diubah.');
    }

    public function destroy(Request $request, BillOfMaterial $bom)
    {
        DB::transaction(function () use ($bom) {
            $bom->bomLines()->delete();
            $bom->delete();
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('bill-of-materials.index') . ($currentQuery ? '?' . $currentQuery : '');

            return Redirect::to($redirectUrl)
                ->with('success', 'BOM berhasil dihapus.');
        } else {
            return Redirect::route('bill-of-materials.index')
                ->with('success', 'BOM berhasil dihapus.');
        }
    }

    public function bulkDelete(Request $request)
    {
        DB::transaction(function () use ($request) {
            foreach ($request->ids as $id) {
                $bom = BillOfMaterial::find($id);
                $bom->bomLines()->delete();
                $bom->delete();
            }
        });

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('bill-of-materials.index') . ($currentQuery ? '?' . $currentQuery : '');

            return Redirect::to($redirectUrl)
                ->with('success', 'BOM berhasil dihapus.');
        }
    }

    private function getFilteredBoms(Request $request)
    {
        $filters = $request->all() ?: Session::get('bill_of_materials.index_filters', []);

        $query = BillOfMaterial::with(['branch', 'finishedProduct'])->withCount('bomLines');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(bom_number)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(description)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('finishedProduct', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  })
                  ->orWhereHas('branch', function ($q) use ($filters) {
                      $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  });
            });
        }

        if (!empty($filters['company_id'])) {
            $query->whereIn('company_id', $filters['company_id']);
        }

        if (!empty($filters['branch_id'])) {
            $query->whereIn('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        if (!empty($filters['finished_product_id'])) {
            $query->where('finished_product_id', $filters['finished_product_id']);
        }

        $sortColumn = $filters['sort'] ?? 'created_at';
        $sortOrder = $filters['order'] ?? 'desc';

        $query->orderBy($sortColumn, $sortOrder);

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $boms = $this->getFilteredBoms($request);
        return Excel::download(new BillOfMaterialsExport($boms), 'bill_of_materials.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $boms = $this->getFilteredBoms($request);
        return Excel::download(new BillOfMaterialsExport($boms), 'bill_of_materials.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $boms = $this->getFilteredBoms($request);
        return Excel::download(new BillOfMaterialsExport($boms), 'bill_of_materials.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }
}
