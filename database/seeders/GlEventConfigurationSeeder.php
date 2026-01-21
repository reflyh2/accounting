<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Enums\AccountingEventCode;
use App\Models\Account;
use App\Models\Company;
use App\Models\GlEventConfiguration;
use App\Models\GlEventConfigurationLine;

/**
 * GlEventConfigurationSeeder - Seeds default GL event configurations for a new tenant.
 * 
 * This seeder must run AFTER AccountSeeder to ensure accounts exist.
 * It creates default journal entry mappings for each accounting event type.
 */
class GlEventConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing company - should be created by SetupTenantDatabase before this seeder runs
        $company = Company::withoutGlobalScopes()->first();
        if (!$company) {
            throw new \RuntimeException('GlEventConfigurationSeeder requires a company to exist. Run SetupTenantDatabase first.');
        }

        // Get required accounts by name for mapping
        $accounts = $this->getAccountsByName([
            // Asset accounts
            'Persediaan Barang Dagang',
            'Piutang Usaha',
            'Transaksi Dalam Pelaksanaan',
            'Persediaan Bahan Baku',
            'Persediaan Barang Setengah Jadi',
            
            // Liability accounts
            'Hutang Usaha dari Pembelian',
            'Hutang Pembelian Belum Difakturkan',
            
            // Revenue accounts
            'Penjualan Barang',
            
            // COGS accounts
            'Harga Pokok Penjualan',
            'Retur Pembelian',
            
            // Expense accounts
            'Koreksi Persediaan',
        ]);

        // Define default GL event configurations with their line mappings
        // Each event has lines with: role, direction (debit/credit), account
        $eventConfigurations = [
            // ============ PURCHASE EVENTS ============
            AccountingEventCode::PURCHASE_GRN_POSTED->value => [
                'description' => 'Journal entry when goods receipt note is posted',
                'lines' => [
                    ['role' => 'inventory', 'direction' => 'debit', 'account_name' => 'Persediaan Barang Dagang'],
                    ['role' => 'grn_clearing', 'direction' => 'credit', 'account_name' => 'Hutang Pembelian Belum Difakturkan'],
                ],
            ],
            AccountingEventCode::PURCHASE_GRN_REVERSED->value => [
                'description' => 'Journal entry when goods receipt note is reversed',
                'lines' => [
                    ['role' => 'grn_clearing', 'direction' => 'debit', 'account_name' => 'Hutang Pembelian Belum Difakturkan'],
                    ['role' => 'inventory', 'direction' => 'credit', 'account_name' => 'Persediaan Barang Dagang'],
                ],
            ],
            AccountingEventCode::PURCHASE_AP_POSTED->value => [
                'description' => 'Journal entry when purchase invoice (AP) is posted',
                'lines' => [
                    ['role' => 'grn_clearing', 'direction' => 'debit', 'account_name' => 'Hutang Pembelian Belum Difakturkan'],
                    ['role' => 'payable', 'direction' => 'credit', 'account_name' => 'Hutang Usaha dari Pembelian'],
                ],
            ],
            AccountingEventCode::PURCHASE_RETURN_POSTED->value => [
                'description' => 'Journal entry when purchase return is posted',
                'lines' => [
                    ['role' => 'payable', 'direction' => 'debit', 'account_name' => 'Hutang Usaha dari Pembelian'],
                    ['role' => 'inventory', 'direction' => 'credit', 'account_name' => 'Persediaan Barang Dagang'],
                ],
            ],
            AccountingEventCode::PURCHASE_RETURN_REVERSED->value => [
                'description' => 'Journal entry when purchase return is reversed',
                'lines' => [
                    ['role' => 'inventory', 'direction' => 'debit', 'account_name' => 'Persediaan Barang Dagang'],
                    ['role' => 'payable', 'direction' => 'credit', 'account_name' => 'Hutang Usaha dari Pembelian'],
                ],
            ],

            // ============ SALES EVENTS ============
            AccountingEventCode::SALES_DELIVERY_POSTED->value => [
                'description' => 'Journal entry when sales delivery is posted',
                'lines' => [
                    ['role' => 'cogs', 'direction' => 'debit', 'account_name' => 'Harga Pokok Penjualan'],
                    ['role' => 'inventory', 'direction' => 'credit', 'account_name' => 'Persediaan Barang Dagang'],
                ],
            ],
            AccountingEventCode::SALES_DELIVERY_REVERSED->value => [
                'description' => 'Journal entry when sales delivery is reversed',
                'lines' => [
                    ['role' => 'inventory', 'direction' => 'debit', 'account_name' => 'Persediaan Barang Dagang'],
                    ['role' => 'cogs', 'direction' => 'credit', 'account_name' => 'Harga Pokok Penjualan'],
                ],
            ],
            AccountingEventCode::SALES_RETURN_POSTED->value => [
                'description' => 'Journal entry when sales return is posted',
                'lines' => [
                    ['role' => 'inventory', 'direction' => 'debit', 'account_name' => 'Persediaan Barang Dagang'],
                    ['role' => 'cogs', 'direction' => 'credit', 'account_name' => 'Harga Pokok Penjualan'],
                ],
            ],
            AccountingEventCode::SALES_AR_POSTED->value => [
                'description' => 'Journal entry when sales invoice (AR) is posted',
                'lines' => [
                    ['role' => 'receivable', 'direction' => 'debit', 'account_name' => 'Piutang Usaha'],
                    ['role' => 'revenue', 'direction' => 'credit', 'account_name' => 'Penjualan Barang'],
                ],
            ],

            // ============ MANUFACTURING EVENTS ============
            AccountingEventCode::MFG_ISSUE_POSTED->value => [
                'description' => 'Journal entry when materials are issued to production',
                'lines' => [
                    ['role' => 'wip', 'direction' => 'debit', 'account_name' => 'Persediaan Barang Setengah Jadi'],
                    ['role' => 'raw_material', 'direction' => 'credit', 'account_name' => 'Persediaan Bahan Baku'],
                ],
            ],
            AccountingEventCode::MFG_RECEIPT_POSTED->value => [
                'description' => 'Journal entry when finished goods are received from production',
                'lines' => [
                    ['role' => 'finished_goods', 'direction' => 'debit', 'account_name' => 'Persediaan Barang Dagang'],
                    ['role' => 'wip', 'direction' => 'credit', 'account_name' => 'Persediaan Barang Setengah Jadi'],
                ],
            ],
            AccountingEventCode::MFG_VARIANCE_POSTED->value => [
                'description' => 'Journal entry when manufacturing variance is posted',
                'lines' => [
                    ['role' => 'variance', 'direction' => 'debit', 'account_name' => 'Koreksi Persediaan'],
                    ['role' => 'wip', 'direction' => 'credit', 'account_name' => 'Persediaan Barang Setengah Jadi'],
                ],
            ],

            // ============ COSTING EVENTS ============
            AccountingEventCode::COGS_RECOGNIZED->value => [
                'description' => 'Journal entry when cost of goods sold is recognized',
                'lines' => [
                    ['role' => 'cogs', 'direction' => 'debit', 'account_name' => 'Harga Pokok Penjualan'],
                    ['role' => 'inventory', 'direction' => 'credit', 'account_name' => 'Persediaan Barang Dagang'],
                ],
            ],
            AccountingEventCode::COST_ALLOCATED->value => [
                'description' => 'Journal entry when costs are allocated',
                'lines' => [
                    ['role' => 'inventory', 'direction' => 'debit', 'account_name' => 'Persediaan Barang Dagang'],
                    ['role' => 'clearing', 'direction' => 'credit', 'account_name' => 'Transaksi Dalam Pelaksanaan'],
                ],
            ],
        ];

        // Create GL event configurations with their lines
        foreach ($eventConfigurations as $eventCode => $config) {
            $glEventConfig = GlEventConfiguration::create([
                'company_id' => $company->id,
                'branch_id' => null, // Company-wide configuration
                'event_code' => $eventCode,
                'is_active' => true,
                'description' => $config['description'],
            ]);

            foreach ($config['lines'] as $line) {
                $account = $accounts[$line['account_name']] ?? null;
                
                if ($account) {
                    GlEventConfigurationLine::create([
                        'gl_event_configuration_id' => $glEventConfig->id,
                        'role' => $line['role'],
                        'direction' => $line['direction'],
                        'account_id' => $account->id,
                    ]);
                }
            }
        }
    }

    /**
     * Get accounts by their names and return as an associative array.
     */
    private function getAccountsByName(array $accountNames): array
    {
        $accounts = Account::whereIn('name', $accountNames)->get();
        
        $result = [];
        foreach ($accounts as $account) {
            $result[$account->name] = $account;
        }
        
        return $result;
    }
}
