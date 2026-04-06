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
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->decimal('secondary_quantity', 18, 3)->nullable()->after('quantity_base');
            $table->string('secondary_uom_label', 40)->nullable()->after('secondary_quantity');
        });

        Schema::table('sales_delivery_lines', function (Blueprint $table) {
            $table->decimal('secondary_quantity', 18, 3)->nullable()->after('quantity_base');
            $table->string('secondary_uom_label', 40)->nullable()->after('secondary_quantity');
        });

        Schema::table('sales_invoice_lines', function (Blueprint $table) {
            $table->decimal('secondary_quantity', 18, 3)->nullable()->after('quantity_base');
            $table->string('secondary_uom_label', 40)->nullable()->after('secondary_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->dropColumn(['secondary_quantity', 'secondary_uom_label']);
        });

        Schema::table('sales_delivery_lines', function (Blueprint $table) {
            $table->dropColumn(['secondary_quantity', 'secondary_uom_label']);
        });

        Schema::table('sales_invoice_lines', function (Blueprint $table) {
            $table->dropColumn(['secondary_quantity', 'secondary_uom_label']);
        });
    }
};
