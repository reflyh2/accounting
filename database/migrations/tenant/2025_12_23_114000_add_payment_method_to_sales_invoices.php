<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('notes');
            $table->foreignId('company_bank_account_id')
                ->nullable()
                ->after('payment_method')
                ->constrained('company_bank_accounts')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropForeign(['company_bank_account_id']);
            $table->dropColumn(['payment_method', 'company_bank_account_id']);
        });
    }
};
