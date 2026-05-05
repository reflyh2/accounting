<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_allocations', function (Blueprint $table) {
            $table->foreignId('booking_allocation_run_id')->nullable()
                ->constrained('booking_allocation_runs')->cascadeOnUpdate()->nullOnDelete();

            $table->index('booking_allocation_run_id', 'idx_cost_alloc_run');
        });
    }

    public function down(): void
    {
        Schema::table('cost_allocations', function (Blueprint $table) {
            $table->dropIndex('idx_cost_alloc_run');
            $table->dropForeign(['booking_allocation_run_id']);
            $table->dropColumn('booking_allocation_run_id');
        });
    }
};
