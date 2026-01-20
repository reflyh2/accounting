<?php

namespace App\Http\Controllers\Admin;

use App\Models\Company;
use App\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Services\ModuleAccessService;

class AdminCompanyController extends Controller
{
    protected ModuleAccessService $moduleService;

    public function __construct(ModuleAccessService $moduleService)
    {
        $this->moduleService = $moduleService;
    }

    /**
     * List companies for a tenant.
     */
    public function index(Tenant $tenant): Response
    {
        $companies = [];

        try {
            $tenant->run(function () use (&$companies) {
                $companies = Company::withoutGlobalScope('userCompanies')
                    ->withCount('branches')
                    ->get()
                    ->map(function ($company) {
                        return [
                            'id' => $company->id,
                            'name' => $company->name,
                            'legal_name' => $company->legal_name,
                            'branches_count' => $company->branches_count,
                            'enabled_modules' => $company->enabled_modules,
                            'modules_count' => $company->enabled_modules === null 
                                ? count($this->moduleService->getModuleKeys())
                                : count($company->enabled_modules),
                            'created_at' => $company->created_at->format('Y-m-d'),
                        ];
                    });
            });
        } catch (\Exception $e) {
            // Tenant database may not be set up yet
        }

        return Inertia::render('Admin/Companies/Index', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->data['name'] ?? $tenant->id,
            ],
            'companies' => $companies,
            'totalModules' => count($this->moduleService->getModuleKeys()),
        ]);
    }

    /**
     * Show module configuration for a company.
     */
    public function modules(Tenant $tenant, int $companyId): Response
    {
        $company = null;

        $tenant->run(function () use ($companyId, &$company) {
            $company = Company::withoutGlobalScope('userCompanies')->find($companyId);
        });

        if (!$company) {
            abort(404, 'Company not found');
        }

        $allModules = $this->moduleService->getAvailableModules();
        $enabledModules = $company->enabled_modules ?? array_keys($allModules);

        return Inertia::render('Admin/Companies/Modules', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->data['name'] ?? $tenant->id,
            ],
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
            ],
            'allModules' => $allModules,
            'enabledModules' => $enabledModules,
        ]);
    }

    /**
     * Update module configuration for a company.
     */
    public function updateModules(Request $request, Tenant $tenant, int $companyId): RedirectResponse
    {
        $validated = $request->validate([
            'enabled_modules' => 'array',
            'enabled_modules.*' => 'string|in:' . implode(',', $this->moduleService->getModuleKeys()),
        ]);

        $tenant->run(function () use ($companyId, $validated) {
            $company = Company::withoutGlobalScope('userCompanies')->find($companyId);
            
            if ($company) {
                $modules = $validated['enabled_modules'] ?? [];
                
                // If all modules are selected, store null (meaning all enabled)
                if (count($modules) === count($this->moduleService->getModuleKeys())) {
                    $company->enabled_modules = null;
                } else {
                    $company->enabled_modules = array_values($modules);
                }
                
                $company->save();
            }
        });

        return redirect()->route('admin.tenants.companies.index', $tenant)
            ->with('success', 'Konfigurasi modul berhasil diperbarui.');
    }
}
