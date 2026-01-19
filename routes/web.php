<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegisterTenantController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AssetFinancingAgreementController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminTenantController;
use Illuminate\Foundation\Application;

Route::get('/lang/{locale}', function ($locale) {
    if (in_array($locale, ['en', 'id'])) {
        session(['locale' => $locale]);
    }
    return redirect()->back();
})->name('lang.switch');

Route::get('/', function () {
    app()->setLocale(session('locale', config('app.locale')));
    return view('marketing.home', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::middleware(['web', 'guest'])->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])
        ->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
    Route::get('/dashboard', [AuthController::class, 'dashboard'])
        ->name('central.dashboard');
    Route::get('/register-tenant', [RegisterTenantController::class, 'create'])->name('register.tenant');
    Route::post('/register-tenant', [RegisterTenantController::class, 'store'])->name('store.tenant');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Routes for the admin dashboard. Uses a separate 'admin' guard for
| authentication, completely isolated from regular user auth.
|
*/

// Admin guest routes (login page)
Route::middleware(['web'])->prefix('admin')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('admin.login.submit');
});

// Admin authenticated routes
Route::middleware(['web', 'auth:admin'])->prefix('admin')->group(function () {
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
    Route::get('/tenants', [AdminTenantController::class, 'index'])->name('admin.tenants.index');
    Route::get('/tenants/{tenant}/edit', [AdminTenantController::class, 'edit'])->name('admin.tenants.edit');
    Route::patch('/tenants/{tenant}', [AdminTenantController::class, 'update'])->name('admin.tenants.update');
});
