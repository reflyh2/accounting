<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add cost_item_id and remove cost_pool_id from sales_order_costs
        Schema::table('sales_order_costs', function (Blueprint $table) {
            $table->foreignId('cost_item_id')->nullable()->after('sales_order_id')->constrained('cost_items');
            $table->dropForeign(['cost_pool_id']);
            $table->dropColumn('cost_pool_id');
        });

        // Add cost_item_id and remove cost_pool_id from sales_invoice_costs
        Schema::table('sales_invoice_costs', function (Blueprint $table) {
            $table->foreignId('cost_item_id')->nullable()->after('sales_invoice_id')->constrained('cost_items');
            $table->dropForeign(['cost_pool_id']);
            $table->dropColumn('cost_pool_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_costs', function (Blueprint $table) {
            $table->foreignId('cost_pool_id')->nullable()->after('sales_order_id')->constrained('cost_pools');
            $table->dropForeign(['cost_item_id']);
            $table->dropColumn('cost_item_id');
        });

        Schema::table('sales_invoice_costs', function (Blueprint $table) {
            $table->foreignId('cost_pool_id')->nullable()->after('sales_invoice_id')->constrained('cost_pools');
            $table->dropForeign(['cost_item_id']);
            $table->dropColumn('cost_item_id');
        });
    }
};
