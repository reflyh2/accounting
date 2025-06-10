<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\User;
use App\Models\Currency;

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
        $tenantUser = null;
        $permissions = [];

        if ($centralUser) {
            // Get the current tenant
            $tenant = tenant();
            
            if ($tenant) {
                // Find the corresponding tenant user
                $tenantUser = User::where('global_id', $centralUser->global_id)->first();
                
                if ($tenantUser) {
                    // Get all permissions for the tenant user
                    $permissions = $tenantUser->getAllPermissions()->pluck('name');
                }
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $centralUser,
                'tenantUser' => $tenantUser,
                'permissions' => $permissions,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'hash' => hash('sha256', now()),
            ],
            'primaryCurrency' => Currency::where('is_primary', true)->first(),
        ];
    }
}
