<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Stancl\Tenancy\Database\Models\Domain;
use App\Models\CentralUser;

class RegisterTenantController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/RegisterTenant', [
            'central_domain' => config('tenancy.main_domain'), 
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
            'company_name' => 'required|string|max:255',
            'company_address' => 'nullable|string|max:500',
            'company_city' => 'nullable|string|max:100',
            'company_province' => 'nullable|string|max:100',
            'company_postal_code' => 'nullable|string|max:20',
            'company_phone' => 'nullable|string|max:50',
        ]);

        $centralUser = CentralUser::where('global_id', Auth::user()->global_id)->first();

        // Create tenant with setup data stored in 'data' JSON column
        $tenant = Tenant::create([
            'id' => $request->subdomain,
            'name' => $request->tenant_name,
            // Company information
            'company_name' => $request->company_name,
            'company_address' => $request->company_address,
            'company_city' => $request->company_city,
            'company_province' => $request->company_province,
            'company_postal_code' => $request->company_postal_code,
            'company_phone' => $request->company_phone,
            // Creator information for user creation in tenant DB
            'creator_global_id' => $centralUser->global_id,
            'creator_name' => $centralUser->name,
            'creator_email' => $centralUser->email,
            'creator_password' => $centralUser->password,
        ]);

        $domain = $tenant->domains()->create([
            'domain' => $request->subdomain,
            'is_primary' => true,
        ]);

        $centralUser->tenants()->attach($tenant);

        return redirect()->route('central.dashboard')->with('success', 'Perusahaan berhasil dibuat. Silahkan tunggu sampai proses selesai.');
    }
}

