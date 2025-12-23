<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create pivot table for sales invoice <-> sales order many-to-many
        Schema::create('sales_invoice_sales_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_invoice_id')
                ->constrained('sales_invoices')
                ->cascadeOnDelete();
            $table->foreignId('sales_order_id')
                ->constrained('sales_orders')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['sales_invoice_id', 'sales_order_id'], 'si_so_unique');
        });

        // Remove old sales_order_id column from sales_invoices
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropForeign(['sales_order_id']);
            $table->dropColumn('sales_order_id');
        });
    }

    public function down(): void
    {
        // Re-add sales_order_id column
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->foreignId('sales_order_id')
                ->nullable()
                ->after('id')
                ->constrained('sales_orders')
                ->nullOnDelete();
        });

        Schema::dropIfExists('sales_invoice_sales_order');
    }
};
