<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_delivery_lines', function (Blueprint $table) {
            $table->decimal('quantity_returned', 18, 3)->default(0)->after('quantity');
            $table->decimal('amount_returned', 18, 2)->default(0)->after('line_total');
        });
    }

    public function down(): void
    {
        Schema::table('sales_delivery_lines', function (Blueprint $table) {
            $table->dropColumn(['quantity_returned', 'amount_returned']);
        });
    }
};
