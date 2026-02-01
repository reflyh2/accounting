<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\PaymentTerm;
use Illuminate\Database\Seeder;

class PaymentTermSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultTerms = [
            ['code' => 'COD', 'name' => 'Cash on Delivery', 'days' => 0],
            ['code' => 'NET7', 'name' => 'Net 7 Days', 'days' => 7],
            ['code' => 'NET15', 'name' => 'Net 15 Days', 'days' => 15],
            ['code' => 'NET30', 'name' => 'Net 30 Days', 'days' => 30],
            ['code' => 'NET45', 'name' => 'Net 45 Days', 'days' => 45],
            ['code' => 'NET60', 'name' => 'Net 60 Days', 'days' => 60],
            ['code' => 'NET90', 'name' => 'Net 90 Days', 'days' => 90],
        ];

        $companies = Company::all();

        foreach ($companies as $company) {
            foreach ($defaultTerms as $term) {
                PaymentTerm::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'code' => $term['code'],
                    ],
                    [
                        'name' => $term['name'],
                        'days' => $term['days'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
