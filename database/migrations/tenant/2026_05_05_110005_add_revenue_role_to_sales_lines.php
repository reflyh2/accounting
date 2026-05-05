<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            // revenue_role values: gross_revenue (default/null), commission_revenue, passthrough_supplier
            $table->string('revenue_role', 40)->nullable()->after('booking_line_id');
        });

        Schema::table('sales_invoice_lines', function (Blueprint $table) {
            $table->string('revenue_role', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoice_lines', function (Blueprint $table) {
            $table->dropColumn('revenue_role');
        });

        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->dropColumn('revenue_role');
        });
    }
};
