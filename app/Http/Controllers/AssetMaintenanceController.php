<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetMaintenanceRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class AssetMaintenanceController extends Controller
{
    public function index(Request $request, Asset $asset)
    {
        $filters = $request->all() ?: Session::get('asset_maintenance.index_filters', []);
        Session::put('asset_maintenance.index_filters', $filters);

        $query = $asset->maintenanceRecords();

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(maintenance_type)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(description)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(performed_by)'), 'like', '%' . strtolower($filters['search']) . '%');
            });
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('maintenance_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('maintenance_date', '<=', $filters['to_date']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'maintenance_date';
        $sortOrder = $filters['order'] ?? 'desc';

        $query->orderBy($sortColumn, $sortOrder);

        $maintenanceRecords = $query->paginate($perPage)->appends(request()->query());

        return Inertia::render('AssetMaintenance/Index', [
            'asset' => $asset->load('branch.branchGroup.company', 'category'),
            'maintenanceRecords' => $maintenanceRecords,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
        ]);
    }

    public function create(Asset $asset)
    {
        $filters = Session::get('asset_maintenance.index_filters', []);
        
        return Inertia::render('AssetMaintenance/Create', [
            'asset' => $asset->load('branch.branchGroup.company'),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request, Asset $asset)
    {
        $validated = $request->validate([
            'maintenance_date' => 'required|date',
            'maintenance_type' => 'required|string|max:255',
            'cost' => 'required|numeric|min:0',
            'description' => 'required|string',
            'performed_by' => 'nullable|string|max:255',
            'next_maintenance_date' => 'nullable|date|after:maintenance_date',
        ]);

        $maintenanceRecord = $asset->maintenanceRecords()->create($validated);

        // Update asset status to 'maintenance' if not already
        if ($asset->status !== 'maintenance') {
            $asset->update(['status' => 'maintenance']);
        }

        if ($request->input('create_another', false)) {
            return redirect()->route('asset-maintenance.create', $asset->id)
                ->with('success', 'Catatan pemeliharaan berhasil dibuat. Silakan buat catatan lainnya.');
        }

        return redirect()->route('asset-maintenance.index', ['asset_id' => $asset->id, 'maintenance_id' => $maintenanceRecord->id])
            ->with('success', 'Catatan pemeliharaan berhasil dibuat.');
    }

    public function edit(AssetMaintenanceRecord $maintenanceRecord)
    {
        $filters = Session::get('asset_maintenance.index_filters', []);
        
        return Inertia::render('AssetMaintenance/Edit', [
            'asset' => $maintenanceRecord->asset->load('branch.branchGroup.company'),
            'maintenance' => $maintenanceRecord,
            'filters' => $filters,
        ]);
    }

    public function update(Request $request, AssetMaintenanceRecord $maintenanceRecord)
    {
        $validated = $request->validate([
            'maintenance_date' => 'required|date',
            'maintenance_type' => 'required|string|max:255',
            'cost' => 'required|numeric|min:0',
            'description' => 'required|string',
            'performed_by' => 'nullable|string|max:255',
            'next_maintenance_date' => 'nullable|date|after:maintenance_date',
        ]);

        $maintenanceRecord->update($validated);

        return redirect()->route('asset-maintenance.index', $maintenanceRecord->asset->id)
            ->with('success', 'Catatan pemeliharaan berhasil diubah.');
    }

    public function destroy(Request $request, AssetMaintenanceRecord $maintenanceRecord)
    {
        $maintenanceRecord->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('asset-maintenance.index', $maintenanceRecord->asset->id) . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Catatan pemeliharaan berhasil dihapus.');
        }

        return redirect()->route('asset-maintenance.index', $maintenanceRecord->asset->id)
            ->with('success', 'Catatan pemeliharaan berhasil dihapus.');
    }

    public function complete(Request $request, AssetMaintenanceRecord $maintenanceRecord)
    {
        $maintenanceRecord->update(['completed_at' => now()]);
        
        // Check if there are any ongoing maintenance records
        $hasOngoingMaintenance = $maintenanceRecord->asset->maintenanceRecords()
            ->whereNull('completed_at')
            ->exists();

        // Update asset status back to active if no ongoing maintenance
        if (!$hasOngoingMaintenance) {
            $maintenanceRecord->asset->update(['status' => 'active']);
        }

        return redirect()->route('asset-maintenance.index', $maintenanceRecord->asset->id)
            ->with('success', 'Pemeliharaan berhasil diselesaikan.');
    }

    public function bulkDelete(Request $request, Asset $asset)
    {
        $asset->maintenanceRecords()->whereIn('id', $request->ids)->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('asset-maintenance.index', $asset->id) . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Catatan pemeliharaan berhasil dihapus.');
        }
    }
} 