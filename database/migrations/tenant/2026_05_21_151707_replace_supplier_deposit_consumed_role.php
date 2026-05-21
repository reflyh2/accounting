<?php

use App\Enums\AccountingEventCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1 seeded SUPPLIER_DEPOSIT_CONSUMED/REVERSED with the role 'cogs_booking'
 * for the non-supplier_advance leg. Phase 6 changes that to a generic 'clearing'
 * role because the actual debit/credit account varies per draw (it's whichever
 * supplier_clearing/supplier_payable_passthrough/cost_item.credit_account was
 * credited by the original obligation event). The role is now only a fallback;
 * the router supplies an account_id override on each entry.
 *
 * This migration removes the stale 'cogs_booking' line so re-running
 * GlEventConfigurationSeeder safely inserts the new 'clearing' line without
 * leaving both present.
 */
return new class extends Migration
{
    public function up(): void
    {
        $eventCodes = [
            AccountingEventCode::SUPPLIER_DEPOSIT_CONSUMED->value,
            AccountingEventCode::SUPPLIER_DEPOSIT_CONSUMED_REVERSED->value,
        ];

        $configIds = DB::table('gl_event_configurations')
            ->whereIn('event_code', $eventCodes)
            ->pluck('id');

        if ($configIds->isEmpty()) {
            return;
        }

        DB::table('gl_event_configuration_lines')
            ->whereIn('gl_event_configuration_id', $configIds)
            ->where('role', 'cogs_booking')
            ->delete();
    }

    public function down(): void
    {
        // No-op: the down direction would re-insert stale data with no real
        // recovery value. Re-run GlEventConfigurationSeeder if a rollback is
        // ever needed to restore some baseline.
    }
};
