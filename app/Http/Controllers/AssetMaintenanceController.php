<?php

namespace App\Http\Controllers;

use App\Enums\CostEntrySource;
use App\Enums\CostObjectType;
use App\Exports\AssetMaintenancesExport;
use App\Http\Requests\StoreAssetMaintenanceRequest;
use App\Http\Requests\UpdateAssetMaintenanceRequest;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CostEntry;
use App\Models\CostPool;
use App\Models\Currency;
use App\Models\Partner;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class AssetMaintenanceController extends Controller
{
    public function index(Request $request)
    {
        $branches = Branch::orderBy('name')->get();
        $companies = Company::orderBy('name')->get();
        $assets = Asset::orderBy('name')->get(['id', 'name', 'code']);
        
        $maintenances = $this->getFilteredMaintenances($request);

        return Inertia::render('AssetMaintenances/Index', [
            'maintenances' => $maintenances,
            'filters' => $request->all(),
            'companies' => $companies,
            'branches' => $branches,
            'assets' => $assets,
            'maintenanceTypes' => AssetMaintenance::maintenanceTypes(),
            'statusOptions' => AssetMaintenance::statusOptions(),
            'perPage' => $request->input('per_page', 10),
            'sort' => $request->input('sort', 'maintenance_date'),
            'order' => $request->input('order', 'desc'),
        ]);
    }

    public function create(Request $request)
    {
        $companies = Company::orderBy('name')->get();
        $vendors = Partner::whereHas('roles', fn ($q) => $q->where('role', 'supplier'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('AssetMaintenances/Create', [
            'filters' => $request->all(),
            'companies' => $companies,
            'branches' => fn () => Branch::whereHas('branchGroup', function ($query) use ($request) {
                $query->where('company_id', $request->input('company_id'));
            })->orderBy('name', 'asc')->get(),
            'assets' => fn () => Asset::where('company_id', $request->input('company_id'))
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'branch_id']),
            'vendors' => $vendors,
            'maintenanceTypes' => AssetMaintenance::maintenanceTypes(),
            'statusOptions' => AssetMaintenance::statusOptions(),
        ]);
    }

    public function store(StoreAssetMaintenanceRequest $request)
    {
        $validated = $request->validated();
        $user = User::find(Auth::user()->global_id);

        $validated['created_by'] = $user->global_id;
        $validated['updated_by'] = $user->global_id;

        DB::transaction(function () use (&$maintenance, $validated, $user) {
            $maintenance = AssetMaintenance::create($validated);

            // If completed, create cost entry
            if ($maintenance->status === 'completed' && $maintenance->total_cost > 0) {
                $this->createOrUpdateCostEntry($maintenance, $user);
            }
        });

        $redirect = $request->input('create_another', false)
            ? route('asset-maintenances.create')
            : route('asset-maintenances.index');

        return redirect($redirect)->with('success', 'Pemeliharaan aset berhasil ditambahkan');
    }

    public function show(AssetMaintenance $assetMaintenance)
    {
        $assetMaintenance->load([
            'company',
            'branch',
            'asset',
            'vendor',
            'costEntry',
            'createdByUser',
            'updatedByUser',
        ]);

        return Inertia::render('AssetMaintenances/Show', [
            'maintenance' => $assetMaintenance,
            'filters' => request()->all('search', 'trashed'),
            'maintenanceTypes' => AssetMaintenance::maintenanceTypes(),
            'statusOptions' => AssetMaintenance::statusOptions(),
        ]);
    }

    public function edit(Request $request, AssetMaintenance $assetMaintenance)
    {
        $assetMaintenance->load(['company', 'branch', 'asset', 'vendor']);
        $companies = Company::orderBy('name')->get();
        $vendors = Partner::whereHas('roles', fn ($q) => $q->where('role', 'supplier'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('AssetMaintenances/Edit', [
            'maintenance' => $assetMaintenance,
            'filters' => request()->all('search', 'trashed'),
            'companies' => $companies,
            'branches' => Branch::whereHas('branchGroup', function ($query) use ($assetMaintenance) {
                $query->where('company_id', $assetMaintenance->company_id);
            })->orderBy('name', 'asc')->get(),
            'assets' => Asset::where('company_id', $assetMaintenance->company_id)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'branch_id']),
            'vendors' => $vendors,
            'maintenanceTypes' => AssetMaintenance::maintenanceTypes(),
            'statusOptions' => AssetMaintenance::statusOptions(),
        ]);
    }

    public function update(UpdateAssetMaintenanceRequest $request, AssetMaintenance $assetMaintenance)
    {
        $validated = $request->validated();
        $user = User::find(Auth::user()->global_id);
        $validated['updated_by'] = $user->global_id;

        DB::transaction(function () use ($assetMaintenance, $validated, $user) {
            $oldStatus = $assetMaintenance->status;
            $assetMaintenance->update($validated);

            // Handle cost entry for status changes
            if ($assetMaintenance->status === 'completed' && $assetMaintenance->total_cost > 0) {
                $this->createOrUpdateCostEntry($assetMaintenance, $user);
            } elseif ($assetMaintenance->status === 'cancelled' && $assetMaintenance->cost_entry_id) {
                // Delete the cost entry if cancelled
                CostEntry::where('id', $assetMaintenance->cost_entry_id)->delete();
                $assetMaintenance->update(['cost_entry_id' => null]);
            }
        });

        return redirect()->route('asset-maintenances.index')->with('success', 'Pemeliharaan aset berhasil diperbarui');
    }

    public function destroy(AssetMaintenance $assetMaintenance)
    {
        DB::transaction(function () use ($assetMaintenance) {
            // Delete linked cost entry if exists
            if ($assetMaintenance->cost_entry_id) {
                CostEntry::where('id', $assetMaintenance->cost_entry_id)->delete();
            }
            $assetMaintenance->delete();
        });

        return redirect()->route('asset-maintenances.index')->with('success', 'Pemeliharaan aset berhasil dihapus');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:asset_maintenances,id',
        ]);

        DB::transaction(function () use ($validated) {
            $maintenances = AssetMaintenance::whereIn('id', $validated['ids'])->get();
            $costEntryIds = $maintenances->pluck('cost_entry_id')->filter();
            
            if ($costEntryIds->isNotEmpty()) {
                CostEntry::whereIn('id', $costEntryIds)->delete();
            }
            
            AssetMaintenance::whereIn('id', $validated['ids'])->delete();
        });

        return redirect()->route('asset-maintenances.index')->with('success', 'Pemeliharaan aset berhasil dihapus');
    }

    public function markCompleted(AssetMaintenance $assetMaintenance)
    {
        if ($assetMaintenance->status === 'completed') {
            return back()->with('error', 'Pemeliharaan sudah selesai');
        }

        $user = User::find(Auth::user()->global_id);

        DB::transaction(function () use ($assetMaintenance, $user) {
            $assetMaintenance->update([
                'status' => 'completed',
                'updated_by' => $user->global_id,
            ]);

            // Create cost entry if there's a cost
            if ($assetMaintenance->total_cost > 0) {
                $this->createOrUpdateCostEntry($assetMaintenance, $user);
            }
        });

        return back()->with('success', 'Pemeliharaan berhasil ditandai selesai');
    }

    public function markCancelled(Request $request, AssetMaintenance $assetMaintenance)
    {
        if ($assetMaintenance->status === 'cancelled') {
            return back()->with('error', 'Pemeliharaan sudah dibatalkan');
        }

        $user = User::find(Auth::user()->global_id);

        DB::transaction(function () use ($assetMaintenance, $user) {
            // Delete cost entry if exists
            if ($assetMaintenance->cost_entry_id) {
                CostEntry::where('id', $assetMaintenance->cost_entry_id)->delete();
            }

            $assetMaintenance->update([
                'status' => 'cancelled',
                'cost_entry_id' => null,
                'updated_by' => $user->global_id,
            ]);
        });

        return back()->with('success', 'Pemeliharaan berhasil dibatalkan');
    }

    public function reopen(AssetMaintenance $assetMaintenance)
    {
        if ($assetMaintenance->status === 'draft') {
            return back()->with('error', 'Pemeliharaan sudah dalam status draft');
        }

        $user = User::find(Auth::user()->global_id);

        DB::transaction(function () use ($assetMaintenance, $user) {
            // Delete cost entry if exists (since we're reopening)
            if ($assetMaintenance->cost_entry_id) {
                CostEntry::where('id', $assetMaintenance->cost_entry_id)->delete();
            }

            $assetMaintenance->update([
                'status' => 'draft',
                'cost_entry_id' => null,
                'updated_by' => $user->global_id,
            ]);
        });

        return back()->with('success', 'Pemeliharaan berhasil dibuka kembali');
    }

    public function print(AssetMaintenance $assetMaintenance)
    {
        $assetMaintenance->load(['company', 'branch', 'asset', 'vendor']);
        $pdf = PDF::loadView('prints.asset-maintenance', [
            'maintenance' => $assetMaintenance,
            'maintenanceTypes' => AssetMaintenance::maintenanceTypes(),
            'statusOptions' => AssetMaintenance::statusOptions(),
        ]);
        return $pdf->stream('maintenance-' . $assetMaintenance->code . '.pdf');
    }

    public function exportXLSX(Request $request)
    {
        $maintenances = $this->getExportData($request);
        return Excel::download(new AssetMaintenancesExport($maintenances), 'asset-maintenances.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $maintenances = $this->getExportData($request);
        return Excel::download(new AssetMaintenancesExport($maintenances), 'asset-maintenances.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $maintenances = $this->getExportData($request);
        $pdf = PDF::loadView('exports.asset-maintenances', [
            'maintenances' => $maintenances,
            'maintenanceTypes' => AssetMaintenance::maintenanceTypes(),
            'statusOptions' => AssetMaintenance::statusOptions(),
        ]);
        return $pdf->download('asset-maintenances.pdf');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function getFilteredMaintenances(Request $request)
    {
        $query = AssetMaintenance::with(['company', 'branch', 'asset', 'vendor']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhereHas('asset', fn ($aq) => $aq->where('name', 'like', '%' . $search . '%'));
            });
        }

        if ($request->filled('company_id')) {
            $query->whereIn('company_id', (array) $request->input('company_id'));
        }

        if ($request->filled('branch_id')) {
            $query->whereIn('branch_id', (array) $request->input('branch_id'));
        }

        if ($request->filled('asset_id')) {
            $query->whereIn('asset_id', (array) $request->input('asset_id'));
        }

        if ($request->filled('maintenance_type')) {
            $query->whereIn('maintenance_type', (array) $request->input('maintenance_type'));
        }

        if ($request->filled('status')) {
            $query->whereIn('status', (array) $request->input('status'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('maintenance_date', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('maintenance_date', '<=', $request->input('to_date'));
        }

        $sort = $request->input('sort', 'maintenance_date');
        $order = $request->input('order', 'desc');

        if ($sort === 'asset.name') {
            $query->join('assets', 'asset_maintenances.asset_id', '=', 'assets.id')
                ->select('asset_maintenances.*')
                ->orderBy('assets.name', $order);
        } else {
            $query->orderBy($sort, $order);
        }

        return $query->paginate($request->input('per_page', 10))->withQueryString();
    }

    private function getExportData(Request $request)
    {
        return $this->getFilteredMaintenances($request)->getCollection();
    }

    private function createOrUpdateCostEntry(AssetMaintenance $maintenance, User $user): void
    {
        // Find or create a cost pool for this asset
        $costPool = CostPool::where('company_id', $maintenance->company_id)
            ->where('asset_id', $maintenance->asset_id)
            ->first();

        if (!$costPool) {
            $costPool = CostPool::create([
                'company_id' => $maintenance->company_id,
                'asset_id' => $maintenance->asset_id,
                'code' => 'POOL-AST-' . $maintenance->asset_id,
                'name' => 'Asset Maintenance Pool: ' . $maintenance->asset->name,
                'pool_type' => 'asset',
                'branch_id' => $maintenance->branch_id,
                'is_active' => true,
                'total_accumulated' => 0,
                'total_allocated' => 0,
                'created_by' => $user->global_id,
                'updated_by' => $user->global_id,
            ]);
        }

        // Get the primary currency
        $primaryCurrency = Currency::where('is_primary', true)->first();

        $costEntryData = [
            'company_id' => $maintenance->company_id,
            'source_type' => CostEntrySource::ASSET_MAINTENANCE,
            'source_id' => $maintenance->id,
            'cost_object_type' => CostObjectType::ASSET_INSTANCE,
            'cost_object_id' => $maintenance->asset_id,
            'cost_pool_id' => $costPool->id,
            'currency_id' => $primaryCurrency->id,
            'description' => 'Maintenance: ' . $maintenance->description,
            'amount' => $maintenance->total_cost,
            'amount_base' => $maintenance->total_cost,
            'exchange_rate' => 1,
            'cost_date' => $maintenance->maintenance_date,
            'is_fully_allocated' => false,
            'amount_allocated' => 0,
            'created_by' => $user->global_id,
        ];

        if ($maintenance->cost_entry_id) {
            // Update existing
            $costEntry = CostEntry::find($maintenance->cost_entry_id);
            if ($costEntry) {
                $costEntry->update($costEntryData);
            } else {
                $costEntry = CostEntry::create($costEntryData);
                $maintenance->update(['cost_entry_id' => $costEntry->id]);
            }
        } else {
            // Create new
            $costEntry = CostEntry::create($costEntryData);
            $maintenance->update(['cost_entry_id' => $costEntry->id]);
        }

        // Update the pool's accumulated amount
        $costPool->recordAccumulation((float) $maintenance->total_cost);
    }
}
