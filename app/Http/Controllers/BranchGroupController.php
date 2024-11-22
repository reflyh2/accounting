<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Branch;
use App\Models\Company;
use App\Models\BranchGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\BranchGroupsExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class BranchGroupController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('branch-groups.index_filters', []);
        Session::put('branch-groups.index_filters', $filters);

        $query = BranchGroup::withCount('branches')->with('company');

        if (!empty($filters['search'])) {
            $query->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('company', function($query) use ($filters) {
                      $query->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  });
        }

        if (!empty($filters['company_id'])) {
            $query->whereIn('company_id', $filters['company_id']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        if ($sortColumn === 'branches_count') {
            $query->orderBy('branches_count', $sortOrder);
        } else {
            $query->orderBy($sortColumn, $sortOrder);
        }

        $branchGroups = $query->paginate($perPage)->onEachSide(0)->withQueryString();
        $companies = Company::orderBy('name', 'asc')->get();

        return Inertia::render('BranchGroups/Index', [
            'branchGroups' => $branchGroups,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
            'companies' => $companies,
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('branch-groups.index_filters', []);
        $companies = Company::all();
        
        return Inertia::render('BranchGroups/Create', [
            'filters' => $filters,
            'companies' => $companies,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_id' => 'required|exists:companies,id',
        ]);

        $branchGroup = BranchGroup::create($validated);

        if ($request->input('create_another', false)) {
            return redirect()->route('branch-groups.create')
                ->with('success', 'Kelompok cabang berhasil dibuat. Silakan buat kelompok cabang lainnya.');
        }

        return redirect()->route('branch-groups.show', $branchGroup->id)
            ->with('success', 'Kelompok cabang berhasil dibuat.');
    }

    public function show(Request $request, BranchGroup $branchGroup)
    {
        $filters = Session::get('branch-groups.index_filters', []);
        
        return Inertia::render('BranchGroups/Show', [
            'branchGroup' => $branchGroup->load('branches', 'company'),
            'filters' => $filters,
        ]);
    }

    public function edit(Request $request, BranchGroup $branchGroup)
    {
        $filters = Session::get('branch-groups.index_filters', []);
        $companies = Company::all();
        
        return Inertia::render('BranchGroups/Edit', [
            'branchGroup' => $branchGroup->load('company'),
            'filters' => $filters,
            'companies' => $companies,
        ]);
    }

    public function update(Request $request, BranchGroup $branchGroup)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_id' => 'required|exists:companies,id',
        ]);

        $branchGroup->update($validated);

        return redirect()->route('branch-groups.edit', $branchGroup->id)
            ->with('success', 'Data kelompok cabang berhasil diubah.');
    }

    public function destroy(Request $request, BranchGroup $branchGroup)
    {
        if ($branchGroup->branches()->exists()) {
            return redirect()->back()->with(['error' => 'Kelompok cabang tidak dapat dihapus karena memiliki cabang.']);
        }

        $branchGroup->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('branch-groups.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Data kelompok cabang berhasil dihapus.');
        } else {
            return Redirect::route('branch-groups.index')
                ->with('success', 'Data kelompok cabang berhasil dihapus.');
        }
    }

    public function bulkDelete(Request $request)
    {
        $branchGroupBranchesCount = Branch::whereIn('branch_group_id', $request->ids)->count();

        if ($branchGroupBranchesCount > 0) {
            return redirect()->back()->with(['error' => 'Kelompok cabang tidak dapat dihapus karena memiliki cabang.']);
        }

        BranchGroup::whereIn('id', $request->ids)->delete();        

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('branch-groups.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Data kelompok cabang berhasil dihapus.');
        } else {
            return Redirect::route('branch-groups.index')
                ->with('success', 'Data kelompok cabang berhasil dihapus.');
        }
    }

    private function getFilteredBranchGroups(Request $request)
    {
        $query = BranchGroup::withCount('branches');
        
        if ($request->filled('search')) {
            $query->where(DB::raw('lower(name)'), 'like', '%' . strtolower($request->search) . '%');
        }

        // Apply other filters as needed

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $branchGroups = $this->getFilteredBranchGroups($request);
        return Excel::download(new BranchGroupsExport($branchGroups), 'branch_groups.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $branchGroups = $this->getFilteredBranchGroups($request);
        return Excel::download(new BranchGroupsExport($branchGroups), 'branch_groups.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $branchGroups = $this->getFilteredBranchGroups($request);
        return Excel::download(new BranchGroupsExport($branchGroups), 'branch_groups.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }
}
