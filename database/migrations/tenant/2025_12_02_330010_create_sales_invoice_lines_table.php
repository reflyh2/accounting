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
        Schema::create('sales_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_invoice_id')
                ->constrained('sales_invoices')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('sales_order_line_id')
                ->constrained('sales_order_lines')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreignId('sales_delivery_line_id')
                ->nullable()
                ->constrained('sales_delivery_lines')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->unsignedSmallInteger('line_number');
            $table->string('description');
            $table->string('uom_label', 40)->nullable();
            $table->decimal('quantity', 18, 3);
            $table->decimal('quantity_base', 18, 3)->default(0);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->decimal('line_total_base', 18, 4)->default(0);
            $table->decimal('delivery_value_base', 18, 4)->default(0);
            $table->decimal('revenue_variance', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->timestamps();

            $table->index(['sales_invoice_id', 'line_number'], 'idx_si_lines_invoice_line');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_lines');
    }
};
