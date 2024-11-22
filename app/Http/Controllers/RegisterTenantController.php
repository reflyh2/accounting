<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Stancl\Tenancy\Database\Models\Domain;
use App\Jobs\CreateTenantUser;
use App\Models\CentralUser;

class RegisterTenantController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/RegisterTenant', [
            'central_domain' => config('tenancy.central_domains')[1], 
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'tenant_name' => 'required|string|max:255',
            'subdomain' => 'required|string|lowercase|max:255|unique:'.Domain::class.',domain',
        ]);

        $tenant = Tenant::create([
            'name' => $request->tenant_name,
        ]);

        $domain = $tenant->domains()->create([
            'domain' => $request->subdomain,
            'is_primary' => true,
        ]);

        $centralUser = CentralUser::where('global_id', Auth::user()->global_id)->first();
        $centralUser->tenants()->attach($tenant);

        // Dispatch the job to create the user in the tenant's database
        // CreateTenantUser::dispatch($tenant, $centralUser)->delay(now()->addSeconds(30));

        return redirect()->route('central.dashboard')->with('success', 'Perusahaan berhasil dibuat. Silahkan tunggu sampai proses selesai.');
    }
}
