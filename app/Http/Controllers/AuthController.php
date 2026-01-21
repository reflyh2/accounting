<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CentralUser;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Stancl\Tenancy\Features\UserImpersonation;

class AuthController extends Controller
{
    public function showRegister()
    {
        return Inertia::render('Auth/CentralRegister');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:central_users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = CentralUser::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        Auth::login($user);

        return redirect()->route('central.dashboard');
    }

    public function showLogin()
    {
        return Inertia::render('Auth/CentralLogin');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            return redirect()->intended(route('central.dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    public function dashboard()
    {
        $user = Auth::user();
        $centralDomain = config('tenancy.main_domain');

        $tenants = $user->tenants()->with('primary_domain')->get()->map(function ($tenant) use ($centralDomain, $user) {
            $domain = $tenant->primary_domain ? $tenant->primary_domain->domain : null;

            if ($domain && !Str::contains($domain, '.')) {
                $domain = $domain . '.' . $centralDomain;
            }

            // Generate a new impersonation token
            $impersonationToken = tenancy()->impersonate(
                $tenant,
                $user->global_id,
                $tenant->route('dashboard'),
                'web'
            )->token;

            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'domain' => $domain,
                'impersonation_token' => $impersonationToken,
            ];
        });
        
        return Inertia::render('Central/Dashboard', [
            'tenants' => $tenants,
        ]);
    }
}