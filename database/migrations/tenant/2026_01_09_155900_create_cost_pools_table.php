<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates cost_pools table for grouping indirect costs.
     * Per COSTING.md: Cost pools accumulate indirect costs before allocation.
     */
    public function up(): void
    {
        Schema::create('cost_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->string('code', 50);
            $table->string('name', 100);

            // Pool type: asset, service_overhead, branch_overhead
            $table->string('pool_type', 50);

            // Optional link to specific asset instance
            $table->foreignId('asset_id')
                ->nullable()
                ->constrained('assets')
                ->onUpdate('cascade')
                ->onDelete('set null');

            // Optional link to branch for overhead pools
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->onUpdate('cascade')
                ->onDelete('set null');

            // Allocation rule: rental_days, usage_hours, usage_km, revenue_proportion, quantity_sold
            $table->string('allocation_rule', 50)->default('revenue_proportion');

            // Running totals
            $table->decimal('total_accumulated', 18, 4)->default(0);
            $table->decimal('total_allocated', 18, 4)->default(0);

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')
                ->references('global_id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('updated_by')
                ->references('global_id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            // Indexes
            $table->unique(['company_id', 'code'], 'idx_cost_pool_company_code');
            $table->index(['company_id', 'pool_type'], 'idx_cost_pool_type');
            $table->index('asset_id', 'idx_cost_pool_asset');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_pools');
    }
};
