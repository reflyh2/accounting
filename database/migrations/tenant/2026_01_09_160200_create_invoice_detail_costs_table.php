<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates invoice_detail_costs table linking costs to revenue lines.
     * Per COSTING.md: This is the bridge between accounting and gross margin.
     */
    public function up(): void
    {
        Schema::create('invoice_detail_costs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sales_invoice_line_id')
                ->constrained('sales_invoice_lines')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            // Cost entry (nullable if from inventory layer)
            $table->foreignId('cost_entry_id')
                ->nullable()
                ->constrained('cost_entries')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            // Inventory consumption (for inventory_layer cost model)
            $table->foreignId('inventory_cost_consumption_id')
                ->nullable()
                ->constrained('inventory_cost_consumptions')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            // Cost allocation (for pool-allocated costs)
            $table->foreignId('cost_allocation_id')
                ->nullable()
                ->constrained('cost_allocations')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            // Allocated amount from the cost entry/pool
            $table->decimal('amount', 18, 4);
            $table->decimal('amount_base', 18, 4);

            // Source type for easy querying
            $table->string('cost_source', 30);  // inventory, direct, allocated

            $table->timestamps();

            // Indexes
            $table->index('sales_invoice_line_id', 'idx_idc_invoice_line');
            $table->index('cost_entry_id', 'idx_idc_cost_entry');
            $table->index('cost_source', 'idx_idc_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_detail_costs');
    }
};
