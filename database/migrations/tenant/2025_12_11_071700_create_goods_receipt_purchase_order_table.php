<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_purchase_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')
                ->constrained('goods_receipts')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('purchase_order_id')
                ->constrained('purchase_orders')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->timestamps();

            $table->unique(['goods_receipt_id', 'purchase_order_id'], 'gr_po_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_purchase_order');
    }
};
