<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Inertia\Inertia;
use App\Models\Permission;
use App\Exports\RolesExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->all() ?: Session::get('roles.index_filters', []);
        Session::put('roles.index_filters', $filters);

        $query = Role::query();

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(description)'), 'like', '%' . strtolower($filters['search']) . '%')
                  ->orWhere(DB::raw('lower(access_level)'), 'like', '%' . strtolower($filters['search']) . '%');
            });
        }

        if (!empty($filters['access_level'])) {
            $query->whereIn('access_level', $filters['access_level']);
        }

        $perPage = $filters['per_page'] ?? 10;
        $sortColumn = $filters['sort'] ?? 'name';
        $sortOrder = $filters['order'] ?? 'asc';

        $query->orderBy($sortColumn, $sortOrder);

        $roles = $query->paginate($perPage)->onEachSide(0)->withQueryString();

        // Add human-friendly labels to the roles collection
        $roles->getCollection()->transform(function ($role) {
            $role->access_level_label = $this->getAccessLevelLabel($role->access_level);
            return $role;
        });

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'filters' => $filters,
            'perPage' => $perPage,
            'sort' => $sortColumn,
            'order' => $sortOrder,
        ]);
    }

    private function getAccessLevelLabel($accessLevel)
    {
        $labels = [
            'own' => 'Data Sendiri',
            'branch' => 'Cabang',
            'branch_group' => 'Kelompok Cabang',
            'company' => 'Perusahaan'
        ];

        return $labels[$accessLevel] ?? $accessLevel;
    }

    public function create(Request $request)
    {
        $filters = Session::get('roles.index_filters', []);
        
        return Inertia::render('Roles/Create', [
            'filters' => $filters,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'description' => 'nullable|string',
            'access_level' => 'required|in:own,branch,branch_group,company',
        ]);

        $role = Role::create($validated);

        if ($request->input('create_another', false)) {
            return redirect()->route('roles.create')
                ->with('success', 'Hak akses berhasil dibuat. Silakan buat hak akses lainnya.');
        }

        return redirect()->route('roles.show', $role->id)
            ->with('success', 'Hak akses berhasil dibuat.');
    }

    public function show(Request $request, Role $role)
    {
        $filters = Session::get('roles.index_filters', []);
        
        return Inertia::render('Roles/Show', [
            'role' => $role,
            'filters' => $filters,
        ]);
    }

    public function edit(Request $request, Role $role)
    {
        $filters = Session::get('roles.index_filters', []);
        
        return Inertia::render('Roles/Edit', [
            'role' => $role,
            'filters' => $filters,
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'description' => 'nullable|string',
            'access_level' => 'required|in:own,branch,branch_group,company',
        ]);

        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'access_level' => $validated['access_level'],
        ]);

        return redirect()->route('roles.edit', $role->id)
            ->with('success', 'Hak akses berhasil diubah.');
    }

    public function destroy(Request $request, Role $role)
    {
        if ($role->users()->exists()) {
            return redirect()->back()->with(['error' => 'Hak akses tidak dapat dihapus karena sedang digunakan.']);
        }

        $role->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('roles.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Hak akses berhasil dihapus.');
        } else {
            return Redirect::route('roles.index')
                ->with('success', 'Hak akses berhasil dihapus.');
        }
    }

    private function getFilteredRoles(Request $request)
    {
        $query = Role::query();
        
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where(DB::raw('lower(name)'), 'like', '%' . strtolower($request->search) . '%')
                  ->orWhere(DB::raw('lower(description)'), 'like', '%' . strtolower($request->search) . '%')
                  ->orWhere(DB::raw('lower(access_level)'), 'like', '%' . strtolower($request->search) . '%');
            });
        }

        return $query->withCount('users')->get();
    }

    public function exportXLSX(Request $request)
    {
        $roles = $this->getFilteredRoles($request);
        return Excel::download(new RolesExport($roles), 'roles.xlsx');
    }

    public function exportCSV(Request $request)
    {
        $roles = $this->getFilteredRoles($request);
        return Excel::download(new RolesExport($roles), 'roles.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPDF(Request $request)
    {
        $roles = $this->getFilteredRoles($request);
        return Excel::download(new RolesExport($roles), 'roles.pdf', \Maatwebsite\Excel\Excel::MPDF);
    }

    public function editPermissions(Request $request, Role $role)
    {
        $permissions = Permission::all()->groupBy([
            function ($permission) {
                return explode('.', $permission->name)[0] ?? '';
            },
            function ($permission) {
                return explode('.', $permission->name)[1] ?? '';
            }
        ]);

        return Inertia::render('Roles/Permissions', [
            'role' => $role,
            'rolePermissions' => $role->permissions->pluck('name'),
            'permissions' => $permissions,
        ]);
    }

    public function updatePermissions(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'required|string',
        ]);

        $role->syncPermissions($validated['permissions']);

        return redirect()->route('roles.permissions', $role->id)
            ->with('success', 'Hak akses berhasil disimpan.');
    }

    public function bulkDelete(Request $request)
    {
        $rolesWithUsersCount = Role::whereIn('id', $request->ids)->has('users')->count();

        if ($rolesWithUsersCount > 0) {
            return redirect()->back()->with(['error' => 'Hak akses tidak dapat dihapus karena sedang digunakan.']);
        }

        Role::whereIn('id', $request->ids)->delete();

        if ($request->has('preserveState')) {
            $currentQuery = $request->input('currentQuery', '');
            $redirectUrl = route('roles.index') . ($currentQuery ? '?' . $currentQuery : '');
            
            return Redirect::to($redirectUrl)
                ->with('success', 'Hak akses berhasil dihapus.');
        } else {
            return Redirect::route('roles.index')
                ->with('success', 'Hak akses berhasil dihapus.');
        }
    }
}