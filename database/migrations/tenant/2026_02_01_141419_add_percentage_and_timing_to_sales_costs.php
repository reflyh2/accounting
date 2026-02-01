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
        Schema::table('sales_order_costs', function (Blueprint $table) {
            $table->decimal('percentage', 5, 2)->nullable()->after('description');
            $table->string('apply_timing')->nullable()->after('percentage')->comment('before_tax or after_tax');
        });

        Schema::table('sales_invoice_costs', function (Blueprint $table) {
            $table->decimal('percentage', 5, 2)->nullable()->after('description');
            $table->string('apply_timing')->nullable()->after('percentage')->comment('before_tax or after_tax');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_costs', function (Blueprint $table) {
            $table->dropColumn(['percentage', 'apply_timing']);
        });

        Schema::table('sales_invoice_costs', function (Blueprint $table) {
            $table->dropColumn(['percentage', 'apply_timing']);
        });
    }
};
