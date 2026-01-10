<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the cost_entries table for recording real economic costs.
     * Per COSTING.md: CostEntry represents a real economic cost created from
     * AP invoices, expense claims, payroll, or journals.
     */
    public function up(): void
    {
        Schema::create('cost_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            // Source document (polymorphic - purchase_invoice, journal, etc.)
            $table->string('source_type', 50);
            $table->unsignedBigInteger('source_id');

            // Cost object (where costs accumulate before allocation)
            // Polymorphic: invoice_detail, booking, work_order, job, asset_instance, cost_center
            $table->string('cost_object_type', 50)->nullable();
            $table->unsignedBigInteger('cost_object_id')->nullable();

            // Optional link to product/variant for tracking
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            // Link to cost pool for indirect costs
            $table->foreignId('cost_pool_id')
                ->nullable()
                ->constrained('cost_pools')
                ->onUpdate('cascade')
                ->onDelete('set null');

            // Cost details
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 18, 4);
            $table->foreignId('currency_id')
                ->constrained('currencies')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->decimal('amount_base', 18, 4);  // Amount in base currency

            // Allocation tracking
            $table->decimal('amount_allocated', 18, 4)->default(0);
            $table->boolean('is_fully_allocated')->default(false);

            $table->date('cost_date');
            $table->text('notes')->nullable();

            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')
                ->references('global_id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            // Indexes
            $table->index(['company_id', 'cost_date'], 'idx_cost_entry_company_date');
            $table->index(['source_type', 'source_id'], 'idx_cost_entry_source');
            $table->index(['cost_object_type', 'cost_object_id'], 'idx_cost_entry_object');
            $table->index('is_fully_allocated', 'idx_cost_entry_allocated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_entries');
    }
};
