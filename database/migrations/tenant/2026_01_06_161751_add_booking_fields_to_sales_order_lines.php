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
            $table->foreignId('resource_pool_id')->nullable()->constrained('resource_pools')->nullOnDelete();
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->dropForeign(['resource_pool_id']);
            $table->dropColumn(['resource_pool_id', 'start_date', 'end_date']);
        });
    }
};
