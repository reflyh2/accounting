<?php

namespace App\Services;

use App\Models\Company;

class ModuleAccessService
{
    /**
     * All available modules with their display names.
     */
    public const MODULES = [
        'sales' => [
            'name' => 'Penjualan',
            'description' => 'Sales orders, invoices, returns',
            'icon' => 'ShoppingCartIcon',
        ],
        'booking' => [
            'name' => 'Booking',
            'description' => 'Booking/reservations',
            'icon' => 'CalendarIcon',
        ],
        'purchase' => [
            'name' => 'Pembelian',
            'description' => 'Purchase orders, invoices, returns',
            'icon' => 'TruckIcon',
        ],
        'inventory' => [
            'name' => 'Inventaris',
            'description' => 'Stock management, transfers',
            'icon' => 'CubeIcon',
        ],
        'accounting' => [
            'name' => 'Akuntansi',
            'description' => 'Journals, CoA, reconciliation',
            'icon' => 'CalculatorIcon',
        ],
        'assets' => [
            'name' => 'Aset Tetap',
            'description' => 'Fixed asset management',
            'icon' => 'BuildingOfficeIcon',
        ],
        'manufacturing' => [
            'name' => 'Manufaktur',
            'description' => 'Production, BOMs',
            'icon' => 'CogIcon',
        ],
        'costing' => [
            'name' => 'Costing',
            'description' => 'Cost pools, allocations',
            'icon' => 'ChartPieIcon',
        ],
        'catalog' => [
            'name' => 'Katalog',
            'description' => 'Products, categories, pricing',
            'icon' => 'TagIcon',
        ],
        'settings' => [
            'name' => 'Pengaturan',
            'description' => 'Company settings, users',
            'icon' => 'Cog6ToothIcon',
        ],
    ];

    /**
     * Get all available module definitions.
     */
    public function getAvailableModules(): array
    {
        return self::MODULES;
    }

    /**
     * Get module keys only.
     */
    public function getModuleKeys(): array
    {
        return array_keys(self::MODULES);
    }

    /**
     * Check if a module is enabled for a company.
     */
    public function isModuleEnabled(?Company $company, string $module): bool
    {
        if (!$company) {
            return true; // No company context = allow all
        }

        // null enabled_modules = all modules enabled
        if ($company->enabled_modules === null) {
            return true;
        }

        return in_array($module, $company->enabled_modules, true);
    }

    /**
     * Get enabled modules for a company.
     */
    public function getEnabledModules(?Company $company): array
    {
        if (!$company) {
            return $this->getModuleKeys();
        }

        // null = all modules enabled
        if ($company->enabled_modules === null) {
            return $this->getModuleKeys();
        }

        return $company->enabled_modules;
    }

    /**
     * Get enabled modules with their full definitions.
     */
    public function getEnabledModulesWithDetails(?Company $company): array
    {
        $enabledKeys = $this->getEnabledModules($company);
        $modules = [];

        foreach ($enabledKeys as $key) {
            if (isset(self::MODULES[$key])) {
                $modules[$key] = self::MODULES[$key];
            }
        }

        return $modules;
    }

    /**
     * Set enabled modules for a company.
     */
    public function setEnabledModules(Company $company, ?array $modules): void
    {
        // If all modules are enabled, set to null
        if ($modules === null || count($modules) === count(self::MODULES)) {
            $company->enabled_modules = null;
        } else {
            // Filter to only valid module keys
            $company->enabled_modules = array_values(
                array_intersect($modules, $this->getModuleKeys())
            );
        }

        $company->save();
    }
}
