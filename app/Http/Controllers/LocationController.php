<?php

namespace App\Http\Controllers;

use App\Exports\LocationsExport;
use App\Models\Branch;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class LocationController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('locations.index_filters', []);
        Session::put('locations.index_filters', $filters);

        $query = Location::query()->with('branch');

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(code)'), 'like', '%'.strtolower($filters['search']).'%')
                    ->orWhere(DB::raw('lower(name)'), 'like', '%'.strtolower($filters['search']).'%');
            });
        }

        if (! empty($filters['branch_id'])) {
            $query->whereIn('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['type'])) {
            $query->whereIn('type', $filters['type']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '' && $filters['is_active'] !== null) {
            $query->where('is_active', $filters['is_active']);
        }

        $perPage = $filters['per_page'] ?? 10;

        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        if ($sortColumn === 'branch.name') {
            $query->join('branches', 'locations.branch_id', '=', 'branches.id')
                ->orderBy('branches.name', $sortOrder)
                ->select('locations.*');
        } else {
            $query->orderBy($sortColumn, $sortOrder);
        }

        $locations = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $branches = Branch::withoutGlobalScope('userBranches')->orderBy('name', 'asc')->get();

        return Inertia::render('Locations/Index', [
            'locations' => $locations,
            'branches' => $branches,
            'filters' => $filters,
            'perPage' => $perPage,
            'sortColumn' => $sortColumn,
            'sortOrder' => $sortOrder,
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('locations.index_filters', []);

        return Inertia::render('Locations/Create', [
            'branches' => Branch::withoutGlobalScope('userBranches')->orderBy('name', 'asc')->get(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:locations,code',
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:warehouse,store,room,yard,vehicle',
            'branch_id' => 'required|exists:branches,id',
            'is_active' => 'boolean',
        ]);

        $validated['created_by'] = Auth::user()->global_id;

        $location = Location::create($validated);

        if ($request->input('create_another', false)) {
            return redirect()->route('locations.create')
                ->with('success', 'Data lokasi berhasil dibuat. Silakan buat lokasi lainnya.');
        }

        return redirect()->route('locations.show', $location->id)
            ->with('success', 'Data lokasi berhasil dibuat.');
    }

    public function show(Request $request, $locationId)
    {
        $location = Location::findOrFail($locationId);
        $filters = Session::get('locations.index_filters', []);

        return Inertia::render('Locations/Show', [
            'location' => $location->load('branch'),
            'filters' => $filters,
        ]);
    }

    public function edit(Request $request, $locationId)
    {
        $location = Location::findOrFail($locationId);
        $filters = Session::get('locations.index_filters', []);

        return Inertia::render('Locations/Edit', [
            'location' => $location->load('branch'),
            'branches' => Branch::withoutGlobalScope('userBranches')->orderBy('name', 'asc')->get(),
            'filters' => $filters,
        ]);
    }

    public function update(Request $request, $locationId)
    {
        $location = Location::findOrFail($locationId);

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:locations,code,'.$location->id,
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:warehouse,store,room,yard,vehicle',
            'branch_id' => 'required|exists:branches,id',
            'is_active' => 'boolean',
        ]);

        $validated['updated_by'] = Auth::user()->global_id;

        $location->update($validated);

        return redirect()->route('locations.edit', $location->id)
            ->with('success', 'Data lokasi berhasil diubah.');
    }

    public function destroy(Request $request, $locationId)
    {
        $location = Location::findOrFail($locationId);

        if ($location->inventoryItems()->exists()) {
            return redirect()->back()->with(['error' => 'Lokasi tidak dapat dihapus karena memiliki item inventaris.']);
        }

        $location->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('locations.index').($currentQuery ? '?'.$currentQuery : '');

            return Redirect::to($redirectUrl)
                ->with('success', 'Data lokasi berhasil dihapus.');
        } else {
            return Redirect::route('locations.index')
                ->with('success', 'Data lokasi berhasil dihapus.');
        }
    }

    public function bulkDelete(Request $request)
    {
        $hasInventory = DB::table('inventory_items')
            ->whereIn('location_id', $request->ids)
            ->exists();

        if ($hasInventory) {
            return redirect()->back()->with(['error' => 'Lokasi tidak dapat dihapus karena memiliki item inventaris.']);
        }

        Location::whereIn('id', $request->ids)->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('locations.index').($currentQuery ? '?'.$currentQuery : '');

            return Redirect::to($redirectUrl)
                ->with('success', 'Data lokasi berhasil dihapus.');
        }
    }

    private function getFilteredLocations(Request $request)
    {
        $query = Location::query()->with('branch');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where(DB::raw('lower(code)'), 'like', '%'.strtolower($request->search).'%')
                    ->orWhere(DB::raw('lower(name)'), 'like', '%'.strtolower($request->search).'%');
            });
        }

        if ($request->filled('branch_id')) {
            $query->whereIn('branch_id', $request->branch_id);
        }

        if ($request->filled('type')) {
            $query->whereIn('type', $request->type);
        }

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $locations = $this->getFilteredLocations($request);

        return Excel::download(new LocationsExport($locations), 'locations.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $locations = $this->getFilteredLocations($request);

        return Excel::download(new LocationsExport($locations), 'locations.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $locations = $this->getFilteredLocations($request);

        return Excel::download(new LocationsExport($locations), 'locations.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }
}
