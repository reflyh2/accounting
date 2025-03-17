<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        $permissionGroups = [
            'settings' => [
                'companies',
                'branch-groups',
                'branches',
                'roles',
                'users',
            ],
        ];

        $crudActions = ['view', 'create', 'update', 'delete'];

        foreach ($permissionGroups as $group => $routes) {
            foreach ($routes as $route) {
                foreach ($crudActions as $action) {
                    $permissionName = "{$group}.{$route}.{$action}";
                    
                    // Check if the permission already exists
                    if (!Permission::where('name', $permissionName)->exists()) {
                        Permission::create(['name' => $permissionName]);
                    }
                }
            }
        }

        // Create Super Admin Role and grant all permissions
        $superAdminRole = Role::create([
            'name' => 'Super Administrator',
            'guard_name' => 'web',
            'access_level' => 'company',
            'description' => 'Super Administrator bisa mengakses semua data',
        ]);

        $permissions = Permission::all();
        $superAdminRole->permissions()->attach($permissions);

        // Assign Super Admin Role to first user
        $firstUser = User::first();
        $firstUser->roles()->attach($superAdminRole);
    }
}