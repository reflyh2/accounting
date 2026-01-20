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
        // Permission groups organized by module
        $permissionGroups = [
            // Settings module - basic CRUD
            'settings' => [
                'companies',
                'branch-groups',
                'branches',
                'roles',
                'users',
                'accounting-periods',
                'approval-workflows',
                'partners',
            ],
            // Purchase module - includes document workflow actions
            'purchase' => [
                'purchase_plan',
                'purchase_order',
                'goods_receipt',
                'purchase_invoice',
                'purchase_return',
            ],
            // Sales module - includes document workflow actions
            'sales' => [
                'sales_order',
                'sales_invoice',
                'sales_return',
                'delivery_order',
            ],
            // Inventory module
            'inventory' => [
                'stock',
                'transfer',
                'adjustment',
                'warehouse',
            ],
            // Accounting module
            'accounting' => [
                'journal',
                'payment',
                'receipt',
                'reconciliation',
            ],
            // Manufacturing module
            'manufacturing' => [
                'work_order',
                'bom',
                'production',
            ],
            // Master data module
            'catalog' => [
                'products',
                'accounts',
                'currencies',
            ],
            // Asset module
            'asset' => [
                'asset_category',
                'asset',
                'asset_purchase',
                'asset_rental',
                'asset_sale',
                'asset_disposal',
                'asset_depreciation',
                'asset_transfer',
                'asset_maintenance',
            ],
        ];

        // Standard CRUD actions for all modules
        $crudActions = ['view', 'create', 'update', 'delete'];

        // Document-specific actions for workflow modules
        $documentActions = ['approve', 'post', 'cancel'];

        // Modules that have document workflow actions
        $documentModules = ['purchase', 'sales', 'accounting'];

        foreach ($permissionGroups as $group => $routes) {
            foreach ($routes as $route) {
                // Create standard CRUD permissions for all modules
                foreach ($crudActions as $action) {
                    $permissionName = "{$group}.{$route}.{$action}";
                    
                    // Check if the permission already exists
                    if (!Permission::where('name', $permissionName)->exists()) {
                        Permission::create(['name' => $permissionName]);
                    }
                }

                // Create document workflow actions for document modules
                if (in_array($group, $documentModules)) {
                    foreach ($documentActions as $action) {
                        $permissionName = "{$group}.{$route}.{$action}";
                        
                        if (!Permission::where('name', $permissionName)->exists()) {
                            Permission::create(['name' => $permissionName]);
                        }
                    }
                }
            }
        }

        // Create Super Admin Role and grant all permissions (use firstOrCreate to avoid duplicates)
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'Super Administrator', 'guard_name' => 'web'],
            [
                'access_level' => 'company',
                'description' => 'Super Administrator bisa mengakses semua data',
            ]
        );

        $permissions = Permission::all();
        $superAdminRole->permissions()->syncWithoutDetaching($permissions);

        // Assign Super Admin Role to first user (if exists)
        // Note: When called from TenantSetupSeeder, user may be created separately
        $firstUser = User::first();
        if ($firstUser && !$firstUser->hasRole('Super Administrator')) {
            $firstUser->roles()->attach($superAdminRole);
        }
    }
}