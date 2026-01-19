<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class TenantLimitService
{
    /**
     * Check if tenant can create a new company.
     */
    public function canCreateCompany(): bool
    {
        $tenant = $this->getCurrentTenant();
        if (!$tenant || $tenant->max_companies === null) {
            return true;
        }

        $currentCount = DB::table('companies')->count();
        return $currentCount < $tenant->max_companies;
    }

    /**
     * Check if tenant can create a new branch.
     */
    public function canCreateBranch(): bool
    {
        $tenant = $this->getCurrentTenant();
        if (!$tenant || $tenant->max_branches === null) {
            return true;
        }

        $currentCount = DB::table('branches')->count();
        return $currentCount < $tenant->max_branches;
    }

    /**
     * Check if tenant can create a new user.
     */
    public function canCreateUser(): bool
    {
        $tenant = $this->getCurrentTenant();
        if (!$tenant || $tenant->max_users === null) {
            return true;
        }

        $currentCount = DB::table('users')->count();
        return $currentCount < $tenant->max_users;
    }

    /**
     * Get usage statistics for the current tenant.
     */
    public function getUsageStats(): array
    {
        $tenant = $this->getCurrentTenant();
        
        return [
            'companies' => [
                'current' => DB::table('companies')->count(),
                'max' => $tenant?->max_companies,
            ],
            'branches' => [
                'current' => DB::table('branches')->count(),
                'max' => $tenant?->max_branches,
            ],
            'users' => [
                'current' => DB::table('users')->count(),
                'max' => $tenant?->max_users,
            ],
        ];
    }

    /**
     * Get the current tenant from the central database.
     */
    protected function getCurrentTenant(): ?Tenant
    {
        $tenantId = tenant('id');
        if (!$tenantId) {
            return null;
        }

        // Query the central database for tenant limits
        return Tenant::on('central')->find($tenantId);
    }
}
