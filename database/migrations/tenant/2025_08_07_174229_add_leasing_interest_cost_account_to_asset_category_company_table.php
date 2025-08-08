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
            $table->foreignId('leasing_interest_cost_account_id')->nullable()->constrained('accounts')->onUpdate('cascade')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_category_company', function (Blueprint $table) {
            $table->dropForeign(['leasing_interest_cost_account_id']);
            $table->dropColumn('leasing_interest_cost_account_id');
        });
    }
};
