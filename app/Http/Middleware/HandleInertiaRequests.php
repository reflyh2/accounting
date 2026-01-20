<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\User;
use App\Models\Company;
use App\Models\Currency;
use App\Models\UserSetting;
use App\Services\ModuleAccessService;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $centralUser = $request->user();
        $adminUser = null;
        $tenantUser = null;
        $permissions = [];
        $enabledModules = [];
        $currentCompany = null;

        // Wrap admin user retrieval in try-catch to handle stale sessions
        // with invalid IDs (e.g., integer IDs from before UUID migration)
        try {
            $adminUser = $request->user('admin');
        } catch (\Exception $e) {
            // Invalid session - clear the admin guard session
            \Illuminate\Support\Facades\Auth::guard('admin')->logout();
        }

        if ($centralUser) {
            // Get the current tenant
            $tenant = tenant();
            
            if ($tenant) {
                // Find the corresponding tenant user
                $tenantUser = User::where('global_id', $centralUser->global_id)->first();
                
                if ($tenantUser) {
                    // Get all permissions for the tenant user
                    $permissions = $tenantUser->getAllPermissions()->pluck('name');

                    // Get current company and its enabled modules
                    $currentCompany = $this->getCurrentCompany($tenantUser);
                    if ($currentCompany) {
                        $moduleService = app(ModuleAccessService::class);
                        $enabledModules = $moduleService->getEnabledModules($currentCompany);
                    }
                }
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $centralUser,
                'tenantUser' => $tenantUser,
                'permissions' => $permissions,
                'admin' => $adminUser,
            ],
            'enabledModules' => $enabledModules,
            'currentCompany' => $currentCompany ? [
                'id' => $currentCompany->id,
                'name' => $currentCompany->name,
            ] : null,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'hash' => hash('sha256', now()),
            ],
            'primaryCurrency' => ($tenantUser) ? Currency::where('is_primary', true)->first() : null,
            'onboarding' => $tenantUser ? [
                'completed' => UserSetting::getValue($tenantUser->global_id, 'onboarding_completed', false),
                'skipped' => UserSetting::getValue($tenantUser->global_id, 'onboarding_skipped', false),
                'currentStep' => UserSetting::getValue($tenantUser->global_id, 'onboarding_step', 0),
            ] : null,
        ];
    }

    /**
     * Get the current company from session or user's first company.
     */
    protected function getCurrentCompany(?User $tenantUser): ?Company
    {
        if (!$tenantUser) {
            return null;
        }

        // Try to get company from session
        $companyId = session('current_company_id');
        if ($companyId) {
            $company = Company::withoutGlobalScope('userCompanies')->find($companyId);
            if ($company) {
                return $company;
            }
        }

        // Fall back to user's first accessible company
        $branch = $tenantUser->branches()
            ->with('branchGroup.company')
            ->first();

        if ($branch?->branchGroup?->company) {
            // Store in session for future requests
            session(['current_company_id' => $branch->branchGroup->company->id]);
            return $branch->branchGroup->company;
        }

        return null;
    }
}
