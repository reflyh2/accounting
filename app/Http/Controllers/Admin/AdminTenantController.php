<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use Inertia\Response;
use App\Models\Tenant;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class AdminTenantController extends Controller
{
    /**
     * Display list of all tenants with usage stats.
     */
    public function index(): Response
    {
        $tenants = Tenant::with('domains')->get()->map(function ($tenant) {
            // Get usage stats by running in tenant context
            $usage = $this->getTenantUsage($tenant);

            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'domain' => $tenant->domains->first()?->domain,
                'max_companies' => $tenant->max_companies,
                'max_branches' => $tenant->max_branches,
                'max_users' => $tenant->max_users,
                'current_companies' => $usage['companies'],
                'current_branches' => $usage['branches'],
                'current_users' => $usage['users'],
                'created_at' => $tenant->created_at->format('Y-m-d'),
            ];
        });

        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => $tenants,
        ]);
    }

    /**
     * Show the form for editing the specified tenant limits.
     */
    public function edit(Tenant $tenant): Response
    {
        return Inertia::render('Admin/Tenants/Edit', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'max_companies' => $tenant->max_companies,
                'max_branches' => $tenant->max_branches,
                'max_users' => $tenant->max_users,
            ],
        ]);
    }

    /**
     * Update the specified tenant limits.
     */
    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'max_companies' => 'nullable|integer|min:0',
            'max_branches' => 'nullable|integer|min:0',
            'max_users' => 'nullable|integer|min:0',
        ]);

        // Convert empty strings to null for unlimited
        $tenant->max_companies = $validated['max_companies'] !== '' ? $validated['max_companies'] : null;
        $tenant->max_branches = $validated['max_branches'] !== '' ? $validated['max_branches'] : null;
        $tenant->max_users = $validated['max_users'] !== '' ? $validated['max_users'] : null;
        $tenant->save();

        return redirect()->route('admin.tenants.index')
            ->with('success', 'Tenant limits updated successfully.');
    }

    /**
     * Get usage statistics for a tenant by running queries in tenant context.
     */
    private function getTenantUsage(Tenant $tenant): array
    {
        $usage = [
            'companies' => 0,
            'branches' => 0,
            'users' => 0,
        ];

        try {
            $tenant->run(function () use (&$usage) {
                $usage['companies'] = \Illuminate\Support\Facades\DB::table('companies')->count();
                $usage['branches'] = \Illuminate\Support\Facades\DB::table('branches')->count();
                $usage['users'] = \Illuminate\Support\Facades\DB::table('users')->count();
            });
        } catch (\Exception $e) {
            // Database may not be set up yet for new tenants
        }

        return $usage;
    }
}
