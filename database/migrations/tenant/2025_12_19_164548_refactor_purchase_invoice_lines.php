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
        Schema::table('purchase_invoice_lines', function (Blueprint $table) {
            $table->foreignId('purchase_order_line_id')->nullable()->change();
            
            $table->foreignId('product_variant_id')
                ->nullable()
                ->after('purchase_invoice_id')
                ->constrained('product_variants')
                ->nullOnDelete();

            $table->foreignId('uom_id')
                ->nullable()
                ->after('product_variant_id')
                ->constrained('uoms')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_invoice_lines', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropForeign(['uom_id']);
            $table->dropColumn(['product_variant_id', 'uom_id']);

            // Note: We cannot easily revert purchase_order_line_id to NOT NULL if there are null values.
            // But structurally:
            // $table->foreignId('purchase_order_line_id')->nullable(false)->change(); 
        });
    }
};
