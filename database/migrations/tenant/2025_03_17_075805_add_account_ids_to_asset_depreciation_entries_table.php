<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('asset_depreciation_entries', function (Blueprint $table) {
            $table->foreignId('debit_account_id')->nullable()->constrained('accounts');
            $table->foreignId('credit_account_id')->nullable()->constrained('accounts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_depreciation_entries', function (Blueprint $table) {
            $table->dropForeign(['debit_account_id']);
            $table->dropForeign(['credit_account_id']);
            $table->dropColumn('debit_account_id');
            $table->dropColumn('credit_account_id');
        });
    }
};
