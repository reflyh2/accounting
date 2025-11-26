<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_debt_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('external_debt_payments', 'partner_bank_account_id')) {
                $table->foreignId('partner_bank_account_id')->nullable()->after('payment_method')->constrained('partner_bank_accounts')->onDelete('restrict');
            }
            if (!Schema::hasColumn('external_debt_payments', 'instrument_date')) {
                $table->date('instrument_date')->nullable()->after('partner_bank_account_id');
            }
            if (!Schema::hasColumn('external_debt_payments', 'withdrawal_date')) {
                $table->date('withdrawal_date')->nullable()->after('instrument_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('external_debt_payments', function (Blueprint $table) {
            if (Schema::hasColumn('external_debt_payments', 'withdrawal_date')) {
                $table->dropColumn('withdrawal_date');
            }
            if (Schema::hasColumn('external_debt_payments', 'instrument_date')) {
                $table->dropColumn('instrument_date');
            }
            if (Schema::hasColumn('external_debt_payments', 'partner_bank_account_id')) {
                $table->dropConstrainedForeignId('partner_bank_account_id');
            }
        });
    }
};


