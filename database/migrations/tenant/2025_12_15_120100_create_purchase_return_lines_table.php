<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')
                ->constrained('purchase_returns')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('goods_receipt_line_id')
                ->constrained('goods_receipt_lines')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreignId('purchase_order_line_id')
                ->constrained('purchase_order_lines')
                ->onUpdate('cascade')
                ->onDelete('restrict');
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
            $table->decimal('unit_cost_base', 18, 6);
            $table->decimal('line_total', 18, 2);
            $table->decimal('line_total_base', 18, 4);
            $table->timestamps();

            $table->index('purchase_return_id', 'idx_purchase_return_lines_return');
            $table->index('goods_receipt_line_id', 'idx_purchase_return_lines_grn_line');
            $table->index('purchase_order_line_id', 'idx_purchase_return_lines_po_line');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_lines');
    }
};


