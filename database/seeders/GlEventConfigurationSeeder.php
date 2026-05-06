<?php

namespace Database\Seeders;

use App\Enums\AccountingEventCode;
use App\Models\Account;
use App\Models\Company;
use App\Models\GlEventConfiguration;
use App\Models\GlEventConfigurationLine;
use Illuminate\Database\Seeder;

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
        // Get all companies in the tenant
        $companies = Company::withoutGlobalScopes()->get();
        if ($companies->isEmpty()) {
            throw new \RuntimeException('GlEventConfigurationSeeder requires at least one company to exist. Run SetupTenantDatabase first.');
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
            'PPN Keluaran',
            'PPN Masukan',

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
                    ['role' => 'purchase_price_variance', 'direction' => 'debit', 'account_name' => 'Koreksi Persediaan'],
                    ['role' => 'tax_receivable', 'direction' => 'debit', 'account_name' => 'PPN Masukan'],
                    ['role' => 'payable', 'direction' => 'credit', 'account_name' => 'Hutang Usaha dari Pembelian'],
                ],
            ],
            AccountingEventCode::PURCHASE_AP_REVERSED->value => [
                'description' => 'Journal entry when purchase invoice (AP) is reversed/unposted',
                'lines' => [
                    ['role' => 'payable', 'direction' => 'debit', 'account_name' => 'Hutang Usaha dari Pembelian'],
                    ['role' => 'grn_clearing', 'direction' => 'credit', 'account_name' => 'Hutang Pembelian Belum Difakturkan'],
                    ['role' => 'purchase_price_variance', 'direction' => 'credit', 'account_name' => 'Koreksi Persediaan'],
                    ['role' => 'tax_receivable', 'direction' => 'credit', 'account_name' => 'PPN Masukan'],
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
                    // Shipping charge roles - shipping_charge_credit uses account_id override from delivery
                    ['role' => 'shipping_charge', 'direction' => 'debit', 'account_name' => 'Koreksi Persediaan'],
                    ['role' => 'shipping_charge_credit', 'direction' => 'credit', 'account_name' => 'Piutang Usaha'],
                ],
            ],
            AccountingEventCode::SALES_DELIVERY_REVERSED->value => [
                'description' => 'Journal entry when sales delivery is reversed',
                'lines' => [
                    ['role' => 'inventory', 'direction' => 'debit', 'account_name' => 'Persediaan Barang Dagang'],
                    ['role' => 'cogs', 'direction' => 'credit', 'account_name' => 'Harga Pokok Penjualan'],
                    // Shipping charge reversal roles - shipping_charge_credit uses account_id override from delivery
                    ['role' => 'shipping_charge', 'direction' => 'credit', 'account_name' => 'Koreksi Persediaan'],
                    ['role' => 'shipping_charge_credit', 'direction' => 'debit', 'account_name' => 'Piutang Usaha'],
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
                    ['role' => 'commission_revenue', 'direction' => 'credit', 'account_name' => 'Pendapatan Komisi'],
                    ['role' => 'tax_payable', 'direction' => 'credit', 'account_name' => 'PPN Keluaran'],
                    // Shipping charge roles - both use default GL Event Configuration accounts
                    ['role' => 'shipping_charge_revenue', 'direction' => 'credit', 'account_name' => 'Penjualan Barang'],
                    ['role' => 'shipping_charge_receivable', 'direction' => 'debit', 'account_name' => 'Piutang Usaha'],
                ],
            ],
            AccountingEventCode::SALES_AR_REVERSED->value => [
                'description' => 'Journal entry when sales invoice (AR) is reversed/unposted',
                'lines' => [
                    ['role' => 'revenue', 'direction' => 'debit', 'account_name' => 'Penjualan Barang'],
                    ['role' => 'tax_payable', 'direction' => 'debit', 'account_name' => 'PPN Keluaran'],
                    ['role' => 'receivable', 'direction' => 'credit', 'account_name' => 'Piutang Usaha'],
                    ['role' => 'shipping_charge_revenue', 'direction' => 'debit', 'account_name' => 'Penjualan Barang'],
                    ['role' => 'shipping_charge_receivable', 'direction' => 'credit', 'account_name' => 'Piutang Usaha'],
                ],
            ],
            AccountingEventCode::SALES_INVOICE_COGS_POSTED->value => [
                'description' => 'Journal entry when direct sales invoice issues inventory (COGS)',
                'lines' => [
                    ['role' => 'cogs', 'direction' => 'debit', 'account_name' => 'Harga Pokok Penjualan'],
                    ['role' => 'inventory', 'direction' => 'credit', 'account_name' => 'Persediaan Barang Dagang'],
                ],
            ],
            AccountingEventCode::SALES_INVOICE_COGS_REVERSED->value => [
                'description' => 'Journal entry when direct sales invoice inventory issue is reversed',
                'lines' => [
                    ['role' => 'inventory', 'direction' => 'debit', 'account_name' => 'Persediaan Barang Dagang'],
                    ['role' => 'cogs', 'direction' => 'credit', 'account_name' => 'Harga Pokok Penjualan'],
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

            // ============ INVENTORY EVENTS ============
            AccountingEventCode::INVENTORY_ADJUSTMENT_POSTED->value => [
                'description' => 'Journal entry when inventory adjustment is posted',
                'lines' => [
                    ['role' => 'inventory', 'direction' => 'debit', 'account_name' => 'Persediaan Barang Dagang'],
                    ['role' => 'inventory_variance', 'direction' => 'credit', 'account_name' => 'Koreksi Persediaan'],
                ],
            ],
            AccountingEventCode::INVENTORY_ADJUSTMENT_REVERSED->value => [
                'description' => 'Journal entry when inventory adjustment is reversed/deleted',
                'lines' => [
                    ['role' => 'inventory_variance', 'direction' => 'debit', 'account_name' => 'Koreksi Persediaan'],
                    ['role' => 'inventory', 'direction' => 'credit', 'account_name' => 'Persediaan Barang Dagang'],
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

            // ============ BOOKING EVENTS ============
            AccountingEventCode::BOOKING_DEPOSIT_RECEIVED->value => [
                'description' => 'Journal entry when a booking deposit is received',
                'lines' => [
                    ['role' => 'cash', 'direction' => 'debit', 'account_name' => 'Kas Besar'],
                    ['role' => 'customer_deposit', 'direction' => 'credit', 'account_name' => 'Uang Muka Pelanggan'],
                ],
            ],
            AccountingEventCode::BOOKING_DEPOSIT_REVERSED->value => [
                'description' => 'Journal entry when a booking deposit is reversed/refunded',
                'lines' => [
                    ['role' => 'customer_deposit', 'direction' => 'debit', 'account_name' => 'Uang Muka Pelanggan'],
                    ['role' => 'cash', 'direction' => 'credit', 'account_name' => 'Kas Besar'],
                ],
            ],
            AccountingEventCode::BOOKING_DEPOSIT_APPLIED->value => [
                'description' => 'Journal entry when a held deposit is applied to a posted sales invoice',
                'lines' => [
                    ['role' => 'customer_deposit', 'direction' => 'debit', 'account_name' => 'Uang Muka Pelanggan'],
                    ['role' => 'receivable', 'direction' => 'credit', 'account_name' => 'Piutang Usaha'],
                ],
            ],
            AccountingEventCode::BOOKING_DEPOSIT_APPLIED_REVERSED->value => [
                'description' => 'Journal entry when a deposit application is reversed (e.g. invoice unpost)',
                'lines' => [
                    ['role' => 'receivable', 'direction' => 'debit', 'account_name' => 'Piutang Usaha'],
                    ['role' => 'customer_deposit', 'direction' => 'credit', 'account_name' => 'Uang Muka Pelanggan'],
                ],
            ],
            AccountingEventCode::BOOKING_PRINCIPAL_COGS_POSTED->value => [
                'description' => 'COGS for reseller-mode booking invoice (supplier cost into clearing)',
                'lines' => [
                    ['role' => 'cogs_booking', 'direction' => 'debit', 'account_name' => 'Harga Pokok Penjualan Booking'],
                    ['role' => 'supplier_clearing', 'direction' => 'credit', 'account_name' => 'Hutang Pemasok Booking Belum Difakturkan'],
                ],
            ],
            AccountingEventCode::BOOKING_PRINCIPAL_COGS_REVERSED->value => [
                'description' => 'Reversal of reseller-mode booking COGS',
                'lines' => [
                    ['role' => 'supplier_clearing', 'direction' => 'debit', 'account_name' => 'Hutang Pemasok Booking Belum Difakturkan'],
                    ['role' => 'cogs_booking', 'direction' => 'credit', 'account_name' => 'Harga Pokok Penjualan Booking'],
                ],
            ],
            AccountingEventCode::BOOKING_AGENT_PASSTHROUGH_POSTED->value => [
                'description' => 'Agent net method passthrough portion (customer charge → supplier liability)',
                'lines' => [
                    ['role' => 'receivable_passthrough', 'direction' => 'debit', 'account_name' => 'Piutang Usaha'],
                    ['role' => 'supplier_payable_passthrough', 'direction' => 'credit', 'account_name' => 'Hutang Pemasok Pass-through'],
                ],
            ],
            AccountingEventCode::BOOKING_AGENT_PASSTHROUGH_REVERSED->value => [
                'description' => 'Reversal of agent passthrough posting',
                'lines' => [
                    ['role' => 'supplier_payable_passthrough', 'direction' => 'debit', 'account_name' => 'Hutang Pemasok Pass-through'],
                    ['role' => 'receivable_passthrough', 'direction' => 'credit', 'account_name' => 'Piutang Usaha'],
                ],
            ],
            AccountingEventCode::BOOKING_POOL_COGS_POSTED->value => [
                'description' => 'Self-operated booking COGS recognised from cost pool allocation',
                'lines' => [
                    ['role' => 'cogs_booking', 'direction' => 'debit', 'account_name' => 'Harga Pokok Penjualan Booking'],
                    ['role' => 'cost_pool_clearing', 'direction' => 'credit', 'account_name' => 'Akumulasi Biaya Operasional Dialokasikan'],
                ],
            ],
            AccountingEventCode::BOOKING_POOL_COGS_REVERSED->value => [
                'description' => 'Reversal of self-operated booking pool allocation',
                'lines' => [
                    ['role' => 'cost_pool_clearing', 'direction' => 'debit', 'account_name' => 'Akumulasi Biaya Operasional Dialokasikan'],
                    ['role' => 'cogs_booking', 'direction' => 'credit', 'account_name' => 'Harga Pokok Penjualan Booking'],
                ],
            ],
        ];

        $accountNames = collect($eventConfigurations)->pluck('lines.*.account_name')->flatten()->unique()->toArray();
        $accounts = $this->getAccountsByName($accountNames);

        foreach ($companies as $company) {
            foreach ($eventConfigurations as $eventCode => $config) {
                $glEventConfig = GlEventConfiguration::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'event_code' => $eventCode,
                    ],
                    [
                        'branch_id' => null,
                        'description' => $config['description'],
                        'is_active' => true,
                    ]
                );

                foreach ($config['lines'] as $line) {
                    $account = $accounts[$line['account_name']] ?? null;

                    if ($account) {
                        GlEventConfigurationLine::firstOrCreate([
                            'gl_event_configuration_id' => $glEventConfig->id,
                            'role' => $line['role'],
                        ], [
                            'direction' => $line['direction'],
                            'account_id' => $account->id,
                        ]);
                    }
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
