<?php

namespace App\Http\Controllers;

use App\Exports\UsersExport;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Hash;
use App\Services\TenantLimitService;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('users.index_filters', []);
        Session::put('users.index_filters', $filters);

        $query = User::query()->with(['roles', 'branches.branchGroup.company']);

        $query->select(['users.*', DB::raw('global_id as id')]);
        
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(email)'), 'like', '%' . strtolower($filters['search']) . '%');
            });
        }

        if (!empty($filters['role_id'])) {
            $query->whereHas('roles', function ($q) use ($filters) {
                $q->whereIn('roles.id', $filters['role_id']);
            });
        }

        if (!empty($filters['branch_id'])) {
            $query->whereHas('branches', function ($q) use ($filters) {
                $q->whereIn('branches.id', $filters['branch_id']);
            });
        }

        $perPage = $filters['per_page'] ?? 10;

        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);
        
        $users = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        $roles = Role::orderBy('name', 'asc')->get();
        $branches = Branch::withoutGlobalScope('userBranches')->with('branchGroupAll.companyAll')->orderBy('name', 'asc')->get();
        $companies = Company::withoutGlobalScope('userCompanies')->orderBy('name', 'asc')->get();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'branches' => $branches,
            'companies' => $companies,
            'filters' => $filters,
            'perPage' => $perPage,
            'sortColumn' => $sortColumn,
            'sortOrder' => $sortOrder,
        ]);
    }

    public function create(Request $request)
    {
        $filters = Session::get('users.index_filters', []);
        
        return Inertia::render('Users/Create', [
            'roles' => Role::orderBy('name', 'asc')->get(),
            'branches' => Branch::withoutGlobalScope('userBranches')->with(['branchGroupAll.companyAll', 'branchGroup.company'])->orderBy('name', 'asc')->get(),
            'companies' => Company::withoutGlobalScope('userCompanies')->orderBy('name', 'asc')->get(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request)
    {
        // Check tenant limit before creating
        $limitService = app(TenantLimitService::class);
        if (!$limitService->canCreateUser()) {
            return redirect()->back()
                ->with('error', 'Batas maksimum jumlah pengguna telah tercapai. Silakan hubungi administrator.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'roles' => 'required|array',
            'branches' => 'required|array',
        ]);

        DB::beginTransaction();
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->roles()->sync($validated['roles']);
        $user->branches()->sync($validated['branches']);
        DB::commit();

        if ($request->input('create_another', false)) {
            return redirect()->route('users.create')
                ->with('success', 'Pengguna berhasil dibuat. Silakan buat pengguna lainnya.');
        }

        return redirect()->route('users.show', $user->global_id)
            ->with('success', 'Pengguna berhasil dibuat.');
    }    

    public function show(User $user)
    {
        $filters = Session::get('users.index_filters', []);
        
        return Inertia::render('Users/Show', [
            'user' => $user->load(['roles', 'branches.branchGroup.company']),
            'filters' => $filters,
        ]);
    }

    public function edit(Request $request, User $user)
    {
        $filters = Session::get('users.index_filters', []);

        // $branches = Branch::withoutGlobalScope('userBranches')->with(['branchGroup' => function($query) {
        //     $query->withoutGlobalScope('userBranchGroups');
        // }, 'branchGroup.company' => function($query) {
        //     $query->withoutGlobalScope('userCompanies');
        // }])->orderBy('name', 'asc')->get();
        // dd($branches);

        return Inertia::render('Users/Edit', [
            'user' => $user->load(['roles', 'branches.branchGroup.company']),
            'roles' => Role::orderBy('name', 'asc')->get(),
            'branches' => Branch::withoutGlobalScope('userBranches')->with(['branchGroup' => function($query) {
                $query->withoutGlobalScope('userBranchGroups');
            }, 'branchGroup.company' => function($query) {
                $query->withoutGlobalScope('userCompanies');
            }])->orderBy('name', 'asc')->get(),
            'companies' => Company::withoutGlobalScope('userCompanies')->orderBy('name', 'asc')->get(),
            'filters' => $filters,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->global_id . ',global_id',
            'password' => 'nullable|string|min:8',
            'roles' => 'required|array',
            'branches' => 'required|array',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if (!empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        $user->roles()->sync($validated['roles']);
        $user->branches()->sync($validated['branches']);

        return redirect()->route('users.edit', $user->global_id)->with('success', 'Data pengguna berhasil diubah.');
    }

    public function destroy(Request $request, User $user)
    {
        // Check if the user is associated with any journals
        $journalCount = $user->journals()->count();

        if ($journalCount > 0) {
            return redirect()->back()->with(['error' => 'Data pengguna tidak dapat dihapus karena sedang digunakan.']);
        }

        $user->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('users.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Data pengguna berhasil dihapus.');
        } else {
            return Redirect::route('users.index')
                ->with('success', 'Data pengguna berhasil dihapus.');
        }
    }

    public function bulkDelete(Request $request)
    {
        $usersWithJournalsCount = User::whereIn('global_id', $request->ids)->has('journals')->count();

        if ($usersWithJournalsCount > 0) {
            return redirect()->back()->with(['error' => 'Beberapa pengguna tidak dapat dihapus karena sedang digunakan.']);
        }

        User::whereIn('global_id', $request->ids)->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('users.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Pengguna berhasil dihapus.');
        } else {
            return Redirect::route('users.index')
                ->with('success', 'Pengguna berhasil dihapus.');
        }
    }

    private function getFilteredUsers(Request $request)
    {
        $query = User::query()->with(['roles', 'branches.branchGroup.company']);
        
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($request->search) . '%')
                  ->orWhere(DB::raw('lower(email)'), 'like', '%' . strtolower($request->search) . '%');
            });
        }

        if ($request->filled('role_id')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->whereIn('id', $request->role_id);
            });
        }

        if ($request->filled('branch_id')) {
            $query->whereHas('branches', function ($q) use ($request) {
                $q->whereIn('id', $request->branch_id);
            });
        }

        return $query->get();
    }

    public function exportXLSX(Request $request)
    {
        $users = $this->getFilteredUsers($request);
        return Excel::download(new UsersExport($users), 'users.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $users = $this->getFilteredUsers($request);
        return Excel::download(new UsersExport($users), 'users.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $users = $this->getFilteredUsers($request);
        return Excel::download(new UsersExport($users), 'users.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }
}