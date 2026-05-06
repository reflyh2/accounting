<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('deposit_payment_method', 30)->nullable()->after('deposit_amount');
            $table->foreignId('deposit_company_bank_account_id')->nullable()
                ->after('deposit_payment_method')
                ->constrained('company_bank_accounts')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['deposit_company_bank_account_id']);
            $table->dropColumn(['deposit_payment_method', 'deposit_company_bank_account_id']);
        });
    }
};
