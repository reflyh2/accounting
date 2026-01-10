<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds costing columns to sales_invoice_lines for computed cost data.
     */
    public function up(): void
    {
        Schema::table('sales_invoice_lines', function (Blueprint $table) {
            $table->decimal('unit_cost', 18, 4)->nullable()->after('unit_price');
            $table->decimal('cost_total', 18, 4)->default(0)->after('unit_cost');
            $table->decimal('gross_margin', 18, 4)->nullable()->after('cost_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_invoice_lines', function (Blueprint $table) {
            $table->dropColumn(['unit_cost', 'cost_total', 'gross_margin']);
        });
    }
};
