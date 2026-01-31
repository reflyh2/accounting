<?php

use App\Models\Account;
use App\Models\GlEventConfiguration;
use App\Models\GlEventConfigurationLine;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Find the SALES_AR_POSTED GL Event Configuration
        $config = GlEventConfiguration::where('event_code', 'sales.ar_posted')->first();

        if (! $config) {
            return;
        }

        // Get the default revenue and receivable accounts
        $revenueAccount = Account::where('name', 'Penjualan Barang')->first();
        $receivableAccount = Account::where('name', 'Piutang Usaha')->first();

        if (! $revenueAccount || ! $receivableAccount) {
            return;
        }

        // Add shipping charge roles
        GlEventConfigurationLine::create([
            'gl_event_configuration_id' => $config->id,
            'role' => 'shipping_charge_revenue',
            'direction' => 'credit',
            'account_id' => $revenueAccount->id,
        ]);

        GlEventConfigurationLine::create([
            'gl_event_configuration_id' => $config->id,
            'role' => 'shipping_charge_receivable',
            'direction' => 'debit',
            'account_id' => $receivableAccount->id,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $config = GlEventConfiguration::where('event_code', 'sales.ar_posted')->first();

        if (! $config) {
            return;
        }

        GlEventConfigurationLine::where('gl_event_configuration_id', $config->id)
            ->whereIn('role', ['shipping_charge_revenue', 'shipping_charge_receivable'])
            ->delete();
    }
};
