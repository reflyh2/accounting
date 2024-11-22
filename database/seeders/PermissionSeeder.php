<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

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
    }
}