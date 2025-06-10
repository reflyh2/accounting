<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Journal;
use App\Models\BranchGroup;
use Illuminate\Http\Request;
use App\Exports\BranchesExport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect; // Add this line

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('branches.index_filters', []);
        Session::put('branches.index_filters', $filters);

        $query = Branch::query()->withoutGlobalScope('userBranches')->with(['branchGroup.company']);
        
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(address)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhereHas('branchGroup', function ($q) use ($filters) {
                     $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  })
                  ->orWhereHas('branchGroup.company', function ($q) use ($filters) {
                     $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%');
                  });
            });
        }

        if (!empty($filters['branch_group_id'])) {
            $query->whereIn('branch_group_id', $filters['branch_group_id']);
        }

        if (!empty($filters['company_id'])) {
            $query->whereHas('branchGroup.company', function ($q) use ($filters) {
                $q->whereIn('id', $filters['company_id']);
            });
        }

        $perPage = $filters['per_page'] ?? 10;

        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        if ($sortColumn === 'branch_group.name') {
            $query->join('branch_groups', 'branches.branch_group_id', '=', 'branch_groups.id')
                  ->orderBy('branch_groups.name', $sortOrder)
                  ->select('branches.*');
        } 
        else if ($sortColumn === 'branch_group.company.name') {
            $query->join('branch_groups', 'branches.branch_group_id', '=', 'branch_groups.id')
                  ->join('companies', 'branch_groups.company_id', '=', 'companies.id')
                  ->orderBy('companies.name', $sortOrder)
                  ->select('branches.*');
        }
        else {
            $query->orderBy($sortColumn, $sortOrder);
        }
        $branches = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $companies = Company::orderBy('name', 'asc')->get();

        if (!empty($filters['company_id'])) {
            $branchGroups = BranchGroup::whereIn('company_id', $filters['company_id'])->orderBy('name', 'asc')->get();
        } else {
            $branchGroups = BranchGroup::orderBy('name', 'asc')->get();
        }

        return Inertia::render('Branches/Index', [
            'branches' => $branches,
            'branchGroups' => $branchGroups,
            'companies' => $companies,
            'filters' => $filters,
            'perPage' => $perPage,
            'sortColumn' => $sortColumn,
            'sortOrder' => $sortOrder,
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('branches.index_filters', []);
        
        return Inertia::render('Branches/Create', [
            'branchGroups' => BranchGroup::withoutGlobalScope('userBranchGroups')->orderBy('name', 'asc')->get(),
            'companies' => Company::withoutGlobalScope('userCompanies')->orderBy('name', 'asc')->get(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'branch_group_id' => 'required|exists:branch_groups,id',
        ]);

        $branch = Branch::create($validated);

        if ($request->input('create_another', false)) {
            return redirect()->route('branches.create')
                ->with('success', 'Data cabang berhasil dibuat. Silakan buat cabang lainnya.');
        }

        return redirect()->route('branches.show', $branch->id)
            ->with('success', 'Data cabang berhasil dibuat.');
    }    

    public function show(Request $request, $branchId)
    {
        $branch = Branch::withoutGlobalScope('userBranches')->find($branchId);
        $filters = Session::get('branches.index_filters', []);
        
        return Inertia::render('Branches/Show', [
            'branch' => $branch->load(['branchGroup' => function($query) {
                $query->withoutGlobalScope('userBranchGroups');
            }]),
            'filters' => $filters,
        ]);
    }

    public function edit(Request $request, $branchId)
    {
        $branch = Branch::withoutGlobalScope('userBranches')->find($branchId);
        $filters = Session::get('branches.index_filters', []);

        return Inertia::render('Branches/Edit', [
            'branch' => $branch->load(['branchGroup' => function($query) {
                $query->withoutGlobalScope('userBranchGroups');
            }, 'branchGroup.company' => function($query) {
                $query->withoutGlobalScope('userCompanies');
            }]),
            'branchGroups' => BranchGroup::withoutGlobalScope('userBranchGroups')->orderBy('name', 'asc')->get(),
            'companies' => Company::withoutGlobalScope('userCompanies')->orderBy('name', 'asc')->get(),
            'filters' => $filters,
        ]);
    }

    public function update(Request $request, $branchId)
    {
        $branch = Branch::withoutGlobalScope('userBranches')->find($branchId);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'branch_group_id' => 'required|exists:branch_groups,id',
        ]);

        $branch->update($validated);

        return redirect()->route('branches.edit', $branch->id)->with('success', 'Data cabang berhasil diubah.');;
    }

    public function destroy(Request $request, $branchId)
    {
        $branch = Branch::withoutGlobalScope('userBranches')->find($branchId);
        if ($branch->journals()->exists()) {
            return redirect()->back()->with(['error' => 'Cabang tidak dapat dihapus karena memiliki transaksi.']);
        }

        $branch->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('branches.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Data cabang berhasil dihapus.');
        } else {
            return Redirect::route('branches.index')
                ->with('success', 'Data cabang berhasil dihapus.');
        }
    }

    public function bulkDelete(Request $request)
    {
        $branchJournalsCount = Journal::whereIn('branch_id', $request->ids)->count();

        if ($branchJournalsCount > 0) {
            return redirect()->back()->with(['error' => 'Cabang tidak dapat dihapus karena memiliki transaksi.']);
        }

        Branch::withoutGlobalScope('userBranches')->whereIn('id', $request->ids)->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('branches.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Data cabang berhasil dihapus.');
        }
    }

    private function getFilteredBranches(Request $request)
    {
        $query = Branch::query()->with('branchGroup');
        
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($request->search) . '%')
                  ->orWhere(DB::raw('lower(address)'), 'like', '%' . strtolower($request->search) . '%');
            });
        }

        if ($request->filled('branch_group_id')) {
            $query->whereIn('branch_group_id', $request->branch_group_id);
        }

        // Apply other filters as needed

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $branches = $this->getFilteredBranches($request);
        return Excel::download(new BranchesExport($branches), 'branches.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $branches = $this->getFilteredBranches($request);
        return Excel::download(new BranchesExport($branches), 'branches.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $branches = $this->getFilteredBranches($request);
        return Excel::download(new BranchesExport($branches), 'branches.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }
}
