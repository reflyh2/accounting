<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\Currency;
use App\Models\PriceList;

class PriceListStarterSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::all();
        if ($companies->isEmpty()) {
            return;
        }

        $primaryCurrency = Currency::where('is_primary', true)->first() ?? Currency::first();
        if (!$primaryCurrency) {
            return;
        }

        foreach ($companies as $company) {
            PriceList::query()->updateOrCreate(
                ['code' => 'DEFAULT-'.$company->id],
                [
                    'company_id' => $company->id,
                    'name' => 'Default Price List',
                    'currency_id' => $primaryCurrency->id,
                    'channel' => null,
                    'partner_group_id' => null,
                    'valid_from' => now()->toDateString(),
                    'valid_to' => null,
                    'is_active' => true,
                ]
            );
        }
    }
}


