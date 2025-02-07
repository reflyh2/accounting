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
        Schema::table('asset_categories', function (Blueprint $table) {
            $table->foreignId('fixed_asset_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('purchase_payable_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('accumulated_depreciation_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('depreciation_expense_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('prepaid_rent_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('rent_expense_account_id')->nullable()->constrained('accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_categories', function (Blueprint $table) {
            $table->dropForeign(['fixed_asset_account_id']);
            $table->dropForeign(['purchase_payable_account_id']);
            $table->dropForeign(['accumulated_depreciation_account_id']);
            $table->dropForeign(['depreciation_expense_account_id']);
            $table->dropForeign(['prepaid_rent_account_id']);
            $table->dropForeign(['rent_expense_account_id']);
            
            $table->dropColumn([
                'fixed_asset_account_id',
                'purchase_payable_account_id',
                'accumulated_depreciation_account_id',
                'depreciation_expense_account_id',
                'prepaid_rent_account_id',
                'rent_expense_account_id'
            ]);
        });
    }
};
