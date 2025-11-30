<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_debt_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('internal_debt_payments', 'counterparty_account_id')) {
                $table->foreignId('counterparty_account_id')->nullable()->after('account_id')->constrained('accounts')->onDelete('restrict');
            }
            if (!Schema::hasColumn('internal_debt_payments', 'instrument_date')) {
                $table->date('instrument_date')->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('internal_debt_payments', 'withdrawal_date')) {
                $table->date('withdrawal_date')->nullable()->after('instrument_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('internal_debt_payments', function (Blueprint $table) {
            if (Schema::hasColumn('internal_debt_payments', 'withdrawal_date')) {
                $table->dropColumn('withdrawal_date');
            }
            if (Schema::hasColumn('internal_debt_payments', 'instrument_date')) {
                $table->dropColumn('instrument_date');
            }
            if (Schema::hasColumn('internal_debt_payments', 'counterparty_account_id')) {
                $table->dropConstrainedForeignId('counterparty_account_id');
            }
        });
    }
};


