<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates cost_allocations table for tracking allocation of pool costs.
     * Per COSTING.md: Allocations must be auditable and repeatable.
     */
    public function up(): void
    {
        Schema::create('cost_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cost_pool_id')
                ->constrained('cost_pools')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreignId('sales_invoice_line_id')
                ->constrained('sales_invoice_lines')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            // Allocation details
            $table->decimal('amount', 18, 4);
            $table->decimal('amount_base', 18, 4);

            // Allocation basis for audit trail
            // E.g., "3 rental_days of 10 total", "40% revenue_proportion"
            $table->string('allocation_rule', 50);
            $table->decimal('allocation_numerator', 18, 6);
            $table->decimal('allocation_denominator', 18, 6);
            $table->decimal('allocation_ratio', 18, 8);

            // Period for which costs were allocated
            $table->date('period_start');
            $table->date('period_end');

            $table->text('notes')->nullable();

            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')
                ->references('global_id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            // Indexes
            $table->index('cost_pool_id', 'idx_cost_alloc_pool');
            $table->index('sales_invoice_line_id', 'idx_cost_alloc_line');
            $table->index(['period_start', 'period_end'], 'idx_cost_alloc_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_allocations');
    }
};
