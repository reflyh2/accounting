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
        Schema::table('sales_delivery_lines', function (Blueprint $table) {
            $table->decimal('quantity_invoiced', 18, 3)->default(0)->after('quantity_base');
            $table->decimal('quantity_invoiced_base', 18, 3)->default(0)->after('quantity_invoiced');
            $table->decimal('amount_invoiced', 18, 2)->default(0)->after('quantity_invoiced_base');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_delivery_lines', function (Blueprint $table) {
            $table->dropColumn(['quantity_invoiced', 'quantity_invoiced_base', 'amount_invoiced']);
        });
    }
};
