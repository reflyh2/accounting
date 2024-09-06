<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Stancl\Tenancy\Database\Models\Domain;

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
    public function store(Request $request): RedirectResponse|\Illuminate\Http\Response
    {
        $request->validate([
            'tenant_name' => 'required|string|max:255',
            'subdomain' => 'required|string|lowercase|max:255|unique:'.Domain::class.',domain',
        ]);

        $tenant = Tenant::create([
            'name' => $request->name,
        ]);

        $tenant->domains()->create([
            'domain' => $request->subdomain,
        ]);

        return Inertia::location('http://'.$request->subdomain.'.'.config('tenancy.central_domains')[1]);
    }
}
