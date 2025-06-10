<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('asset_invoice_payments', function (Blueprint $table) {
            $table->foreignId('source_account_id')->nullable()->after('partner_id')->constrained('accounts')->nullOnDelete();
            $table->foreignId('destination_bank_account_id')->nullable()->after('source_account_id')->constrained('partner_bank_accounts')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('asset_invoice_payments', function (Blueprint $table) {
            $table->dropForeign(['destination_bank_account_id']);
            $table->dropForeign(['source_account_id']);
            $table->dropColumn(['destination_bank_account_id', 'source_account_id']);
        });
    }
}; 