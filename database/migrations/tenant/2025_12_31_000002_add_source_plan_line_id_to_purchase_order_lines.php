<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->foreignId('source_plan_line_id')
                ->nullable()
                ->after('purchase_order_id')
                ->constrained('purchase_plan_lines')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropForeign(['source_plan_line_id']);
            $table->dropColumn('source_plan_line_id');
        });
    }
};
