<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')
                ->constrained('sales_orders')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->unsignedSmallInteger('line_number');
            $table->foreignId('product_id')
                ->constrained('products')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->string('description');
            $table->foreignId('uom_id')
                ->constrained('uoms')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreignId('base_uom_id')
                ->constrained('uoms')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->decimal('quantity', 18, 3);
            $table->decimal('quantity_base', 18, 3);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->date('requested_delivery_date')->nullable();
            $table->foreignId('reservation_location_id')
                ->nullable()
                ->constrained('locations')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->decimal('quantity_reserved', 18, 3)->default(0);
            $table->decimal('quantity_reserved_base', 18, 3)->default(0);
            $table->decimal('quantity_delivered', 18, 3)->default(0);
            $table->decimal('quantity_delivered_base', 18, 3)->default(0);
            $table->decimal('quantity_invoiced', 18, 3)->default(0);
            $table->decimal('quantity_invoiced_base', 18, 3)->default(0);
            $table->timestamps();

            $table->index(['sales_order_id', 'line_number'], 'idx_so_lines_order_line');
            $table->index('product_variant_id', 'idx_so_lines_variant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_lines');
    }
};


