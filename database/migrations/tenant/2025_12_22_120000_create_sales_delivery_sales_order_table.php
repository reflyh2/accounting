<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create pivot table for SalesDelivery <-> SalesOrder many-to-many
        Schema::create('sales_delivery_sales_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_delivery_id')
                ->constrained('sales_deliveries')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('sales_order_id')
                ->constrained('sales_orders')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->timestamps();

            $table->unique(['sales_delivery_id', 'sales_order_id'], 'sd_so_unique');
        });

        // Remove old sales_order_id column from sales_deliveries
        Schema::table('sales_deliveries', function (Blueprint $table) {
            $table->dropForeign(['sales_order_id']);
            $table->dropColumn('sales_order_id');
        });
    }

    public function down(): void
    {
        // Re-add sales_order_id column
        Schema::table('sales_deliveries', function (Blueprint $table) {
            $table->foreignId('sales_order_id')
                ->nullable()
                ->after('id')
                ->constrained('sales_orders')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });

        Schema::dropIfExists('sales_delivery_sales_order');
    }
};
