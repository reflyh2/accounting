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
        Schema::table('asset_category_company', function (Blueprint $table) {
            $table->foreignId('asset_sale_profit_account_id')->nullable()->constrained('accounts')->onUpdate('cascade')->onDelete('set null');
            $table->foreignId('asset_sale_loss_account_id')->nullable()->constrained('accounts')->onUpdate('cascade')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_category_company', function (Blueprint $table) {
            $table->dropForeign(['asset_sale_profit_account_id']);
            $table->dropForeign(['asset_sale_loss_account_id']);
            $table->dropColumn(['asset_sale_profit_account_id', 'asset_sale_loss_account_id']);
        });
    }
};
