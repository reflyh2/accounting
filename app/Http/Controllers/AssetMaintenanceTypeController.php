<?php

namespace App\Http\Controllers;

use App\Models\AssetMaintenanceType;
use App\Models\AssetCategory;
use App\Models\Company;
use App\Models\Account;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use App\Exports\AssetMaintenanceTypesExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\AssetMaintenanceTypeRequest;

class AssetMaintenanceTypeController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('asset_maintenance_types.index_filters', []);
        Session::put('asset_maintenance_types.index_filters', $filters);

        $query = AssetMaintenanceType::query()
            ->with([
                'assetCategory',
                'maintenanceCostAccount',
                'companies',
            ])
            ->withCount('maintenanceRecords');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(description)'), 'like', '%' . strtolower($filters['search']) . '%');
            });
        }

        if (!empty($filters['category_id'])) {
            $query->where('asset_category_id', $filters['category_id']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);

        $maintenanceTypes = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        return Inertia::render('AssetMaintenanceTypes/Index', [
            'maintenanceTypes' => $maintenanceTypes,
            'categories' => AssetCategory::orderBy('name')->get(),
            'companies' => Company::orderBy('name')->get(),
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
        ]);
    }

    public function create()
    {
        return Inertia::render('AssetMaintenanceTypes/Create', [
            'filters' => request()->all('search', 'trashed'),
            'categories' => AssetCategory::orderBy('name')->get(),
            'companies' => Company::orderBy('name')->get(),
            'accounts' => Account::where('is_parent', false)->orderBy('code')->get()
        ]);
    }

    public function store(AssetMaintenanceTypeRequest $request)
    {
        DB::beginTransaction();
        try {
            $maintenanceType = AssetMaintenanceType::create([
                'name' => $request->name,
                'asset_category_id' => $request->asset_category_id,
                'maintenance_cost_account_id' => $request->maintenance_cost_account_id,
                'description' => $request->description,
                'maintenance_interval' => $request->maintenance_interval,
                'maintenance_interval_days' => $request->maintenance_interval_days,
            ]);

            if ($request->company_ids) {
                $maintenanceType->companies()->sync($request->company_ids);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with(['error' => $e->getMessage()]);
        }
        DB::commit();

        if ($request->input('create_another', false)) {
            return redirect()->route('asset-maintenance-types.create')
                ->with('success', 'Tipe pemeliharaan aset berhasil dibuat. Silakan buat tipe pemeliharaan lainnya.');
        }

        return redirect()->route('asset-maintenance-types.show', $maintenanceType->id)
            ->with('success', 'Tipe pemeliharaan aset berhasil dibuat.');
    }

    public function show(Request $request, AssetMaintenanceType $assetMaintenanceType)
    {
        $sort = $request->sort ?? 'maintenance_date';
        $order = $request->order ?? 'desc';
        $perPage = $request->per_page ?? 10;

        $maintenanceRecords = $assetMaintenanceType->maintenanceRecords()
            ->with(['asset.branch.branchGroup.company', 'maintenanceType'])
            ->orderBy($sort, $order)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('AssetMaintenanceTypes/Show', [
            'maintenanceType' => $assetMaintenanceType->load([
                'assetCategory',
                'maintenanceCostAccount',
                'companies',
            ]),
            'maintenanceRecords' => $maintenanceRecords,
            'filters' => $request->only(['sort', 'order', 'per_page']),
            'sort' => $sort,
            'order' => $order,
            'perPage' => $perPage,
        ]);
    }

    public function edit(AssetMaintenanceType $assetMaintenanceType)
    {
        return Inertia::render('AssetMaintenanceTypes/Edit', [
            'maintenanceType' => $assetMaintenanceType->load([
                'assetCategory',
                'maintenanceCostAccount',
                'companies',
            ]),
            'filters' => request()->all('search', 'trashed'),
            'categories' => AssetCategory::orderBy('name')->get(),
            'companies' => Company::orderBy('name')->get(),
            'accounts' => Account::where('is_parent', false)->orderBy('code')->get()
        ]);
    }

    public function update(AssetMaintenanceTypeRequest $request, AssetMaintenanceType $assetMaintenanceType)
    {
        DB::transaction(function () use ($request, $assetMaintenanceType) {
            $assetMaintenanceType->update([
                'name' => $request->name,
                'asset_category_id' => $request->asset_category_id,
                'maintenance_cost_account_id' => $request->maintenance_cost_account_id,
                'description' => $request->description,
                'maintenance_interval' => $request->maintenance_interval,
                'maintenance_interval_days' => $request->maintenance_interval_days,
            ]);

            if ($request->company_ids) {
                $assetMaintenanceType->companies()->sync($request->company_ids);
            }
        });

        return redirect()
            ->route('asset-maintenance-types.show', $assetMaintenanceType->id)
            ->with('success', 'Tipe pemeliharaan aset berhasil diubah.');
    }

    public function destroy(Request $request, AssetMaintenanceType $assetMaintenanceType)
    {
        if ($assetMaintenanceType->maintenanceRecords()->exists()) {
            return redirect()->route('asset-maintenance-types.index')
                ->with('error', 'Tipe pemeliharaan tidak dapat dihapus karena masih memiliki catatan pemeliharaan.');
        }

        $assetMaintenanceType->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('asset-maintenance-types.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Tipe pemeliharaan aset berhasil dihapus.');
        }

        return redirect()->route('asset-maintenance-types.index')
            ->with('success', 'Tipe pemeliharaan aset berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        $maintenanceTypes = AssetMaintenanceType::whereIn('id', $request->ids)->withCount('maintenanceRecords')->get();
        
        foreach ($maintenanceTypes as $maintenanceType) {
            if ($maintenanceType->maintenance_records_count > 0) {
                return redirect()->route('asset-maintenance-types.index')
                    ->with('error', "Tipe pemeliharaan '{$maintenanceType->name}' tidak dapat dihapus karena masih memiliki catatan pemeliharaan.");
            }
        }

        AssetMaintenanceType::whereIn('id', $request->ids)->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('asset-maintenance-types.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Tipe pemeliharaan aset berhasil dihapus.');
        }
        
        return redirect()->route('asset-maintenance-types.index')
            ->with('success', 'Tipe pemeliharaan aset berhasil dihapus.');
    }

    public function exportXLSX(Request $request)
    {
        $maintenanceTypes = $this->getFilteredMaintenanceTypes($request);
        return Excel::download(new AssetMaintenanceTypesExport($maintenanceTypes), 'asset-maintenance-types.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $maintenanceTypes = $this->getFilteredMaintenanceTypes($request);
        return Excel::download(new AssetMaintenanceTypesExport($maintenanceTypes), 'asset-maintenance-types.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $maintenanceTypes = $this->getFilteredMaintenanceTypes($request);
        return Excel::download(new AssetMaintenanceTypesExport($maintenanceTypes), 'asset-maintenance-types.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }

    private function getFilteredMaintenanceTypes(Request $request)
    {
        $filters = $request->all() ?: Session::get('asset_maintenance_types.index_filters', []);

        $query = AssetMaintenanceType::with(['assetCategory', 'maintenanceCostAccount'])
            ->withCount('maintenanceRecords');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(description)'), 'like', '%' . strtolower($filters['search']) . '%');
            });
        }

        if (!empty($filters['category_id'])) {
            $query->where('asset_category_id', $filters['category_id']);
        }

        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);

        return $query->get();
    }
} 