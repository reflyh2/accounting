<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Company;
use Illuminate\Http\Request;
use App\Services\ModuleAccessService;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleAccess
{
    protected ModuleAccessService $moduleService;

    public function __construct(ModuleAccessService $moduleService)
    {
        $this->moduleService = $moduleService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $module  The module to check access for
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        // Get current company from session or request
        $company = $this->getCurrentCompany($request);

        if (!$this->moduleService->isModuleEnabled($company, $module)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Modul ini tidak tersedia untuk perusahaan Anda.',
                ], 403);
            }

            return redirect()->route('dashboard')
                ->with('error', 'Modul ini tidak tersedia untuk perusahaan Anda.');
        }

        return $next($request);
    }

    /**
     * Get the current company from session or user context.
     */
    protected function getCurrentCompany(Request $request): ?Company
    {
        // Try to get company from session
        $companyId = session('current_company_id');

        if ($companyId) {
            return Company::withoutGlobalScope('userCompanies')->find($companyId);
        }

        // Fall back to user's first accessible company
        $user = $request->user();
        if ($user) {
            $tenantUser = \App\Models\User::where('global_id', $user->global_id)->first();
            if ($tenantUser) {
                return $tenantUser->branches()
                    ->with('branchGroup.company')
                    ->first()
                    ?->branchGroup
                    ?->company;
            }
        }

        return null;
    }
}
