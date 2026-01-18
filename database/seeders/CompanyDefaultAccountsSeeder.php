<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Company;
use Illuminate\Database\Seeder;

/**
 * CompanyDefaultAccountsSeeder - Sets default accounts on company after chart of accounts is created.
 * 
 * This seeder must run AFTER AccountSeeder to ensure accounts exist.
 */
class CompanyDefaultAccountsSeeder extends Seeder
{
    /**
     * Mapping of company default account fields to account names.
     */
    protected array $defaultAccountMapping = [
        'default_receivable_account_id' => 'Piutang Usaha',
        'default_payable_account_id' => 'Hutang Usaha dari Pembelian',
        'default_revenue_account_id' => 'Penjualan Barang',
        'default_cogs_account_id' => 'Harga Pokok Penjualan',
        'default_retained_earnings_account_id' => 'Laba Ditahan',
        'default_interbranch_receivable_account_id' => 'Piutang Antar Cabang',
        'default_interbranch_payable_account_id' => 'Hutang Antar Cabang',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::withoutGlobalScopes()->first();
        
        if (!$company) {
            throw new \RuntimeException('CompanyDefaultAccountsSeeder requires a company to exist.');
        }

        $updates = [];

        foreach ($this->defaultAccountMapping as $field => $accountName) {
            $account = Account::where('name', $accountName)->first();
            
            if ($account) {
                $updates[$field] = $account->id;
            }
        }

        if (!empty($updates)) {
            $company->update($updates);
        }
    }
}
