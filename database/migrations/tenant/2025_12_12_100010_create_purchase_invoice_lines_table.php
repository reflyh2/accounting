<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')
                ->constrained('purchase_invoices')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('purchase_order_line_id')
                ->constrained('purchase_order_lines')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreignId('goods_receipt_line_id')
                ->nullable()
                ->constrained('goods_receipt_lines')
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
            $table->decimal('grn_value_base', 18, 4)->default(0);
            $table->decimal('ppv_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->timestamps();

            $table->index(['purchase_invoice_id', 'line_number'], 'idx_pi_lines_invoice_line');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_lines');
    }
};

