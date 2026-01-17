<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\BranchGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

/**
 * SetupTenantDatabase - Sets up all initial data for a new tenant.
 * 
 * This job runs after tenant database migration and creates:
 * - The tenant user (from central user data)
 * - Primary currency (IDR)
 * - Initial company with branch group and branch
 * - Seeds all required reference data
 */
class SetupTenantDatabase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function handle(): void
    {
        $this->tenant->run(function () {
            // 1. Create primary currency first (needed by accounts)
            $currency = $this->createPrimaryCurrency();

            // 2. Create user and company FIRST (before seeders, so seeders find them)
            DB::transaction(function () use ($currency) {
                // Create the tenant user from stored creator data
                $user = $this->createUser();

                // Create company with branch group and branch
                $company = $this->createCompanyWithBranch($user, $currency);
            });

            // 3. Run seeders for reference data (permissions, accounts, etc.)
            // Seeders will now find the existing company and currency
            $this->runSeeders();

            // 4. Assign Super Administrator role to user (after PermissionSeeder creates the role)
            DB::transaction(function () {
                $user = User::first();
                if ($user) {
                    $this->assignSuperAdminRole($user);
                }
            });
        });
    }

    /**
     * Create the tenant user from central user data stored on tenant.
     * 
     * We disable resource syncing because the central user already exists.
     */
    protected function createUser(): User
    {
        $data = $this->tenant->toArray() ?? [];
        $user = null;
        
        // Get creator data with fallbacks
        $globalId = $data['creator_global_id'] ?? \Illuminate\Support\Str::uuid()->toString();
        $name = $data['creator_name'] ?? 'Admin';
        $email = $data['creator_email'] ?? 'admin@example.com';
        $password = $data['creator_password'] ?? bcrypt('password');
        
        // Temporarily disable resource syncing to avoid duplicate central_users entry
        // The central user already exists, we just need to create the tenant user
        User::withoutEvents(function () use ($globalId, $name, $email, $password, &$user) {
            $user = User::firstOrCreate(
                ['global_id' => $globalId],
                [
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'email_verified_at' => now(),
                ]
            );
        });
        
        return $user;
    }

    /**
     * Create primary currency (IDR - Indonesian Rupiah).
     */
    protected function createPrimaryCurrency(): Currency
    {
        return Currency::firstOrCreate(
            ['code' => 'IDR'],
            [
                'name' => 'Indonesian Rupiah',
                'symbol' => 'Rp',
                'is_primary' => true,
            ]
        );
    }

    /**
     * Create the initial company with branch group and branch.
     */
    protected function createCompanyWithBranch(User $user, Currency $currency): Company
    {
        $data = $this->tenant->toArray() ?? [];

        // Create company using form data
        $company = Company::create([
            'name' => $data['company_name'] ?? $this->tenant->name ?? 'Default Company',
            'legal_name' => $data['company_name'] ?? $this->tenant->name ?? 'Default Company',
            'address' => $data['company_address'] ?? '-',
            'city' => $data['company_city'] ?? '-',
            'province' => $data['company_province'] ?? '-',
            'postal_code' => $data['company_postal_code'] ?? '-',
            'phone' => $data['company_phone'] ?? '-',
        ]);

        // Link company to currency with exchange rate 1
        \App\Models\CompanyCurrencyRate::create([
            'company_id' => $company->id,
            'currency_id' => $currency->id,
            'exchange_rate' => 1,
        ]);

        // Create main branch group
        $branchGroup = BranchGroup::create([
            'name' => 'Pusat',
            'company_id' => $company->id,
        ]);

        // Create main branch with company address
        $branch = Branch::create([
            'name' => 'Kantor Pusat',
            'address' => $company->address,
            'branch_group_id' => $branchGroup->id,
        ]);

        // Attach user to branch
        $user->branches()->attach($branch);

        return $company;
    }

    /**
     * Run the tenant setup seeders.
     */
    protected function runSeeders(): void
    {
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\TenantSetupSeeder',
            '--force' => true,
        ]);
    }

    /**
     * Assign Super Administrator role to the user.
     */
    protected function assignSuperAdminRole(User $user): void
    {
        // The PermissionSeeder creates the role and assigns it to first user.
        // If that doesn't work, we ensure the user has the role.
        $role = \App\Models\Role::where('name', 'Super Administrator')->first();
        
        if ($role && !$user->hasRole('Super Administrator')) {
            $user->roles()->attach($role);
        }
    }
}
