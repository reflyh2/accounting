<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->decimal('quantity_received', 18, 3)
                ->default(0)
                ->after('quantity');
            $table->decimal('quantity_received_base', 18, 3)
                ->default(0)
                ->after('quantity_base');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropColumn(['quantity_received', 'quantity_received_base']);
        });
    }
};


