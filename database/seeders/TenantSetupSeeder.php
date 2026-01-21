<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * TenantSetupSeeder - Seeds all essential data for a new tenant.
 * 
 * This seeder is called after tenant database migration to set up
 * all the required data structures for the accounting application.
 */
class TenantSetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            // Core permissions and roles
            PermissionSeeder::class,
            
            // Chart of accounts (requires Company to exist)
            AccountSeeder::class,
            
            // Set default accounts on company (requires accounts to exist)
            CompanyDefaultAccountsSeeder::class,
            
            // GL Event configurations (requires accounts to exist)
            GlEventConfigurationSeeder::class,
            
            // Units of measure
            UomStarterSeeder::class,
            
            // Tax configuration (Indonesia)
            TaxStarterSeeder::class,
            
            // Product attribute sets
            AttributeSetsSeeder::class,
            
            // Product type templates
            ProductTypeTemplatesSeeder::class,
            
            // Default price lists
            PriceListStarterSeeder::class,
        ]);
    }
}
