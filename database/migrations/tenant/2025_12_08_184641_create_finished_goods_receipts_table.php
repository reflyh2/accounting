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
        Schema::create('finished_goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 50)->unique();
            $table->foreignId('work_order_id')
                ->constrained('work_orders')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreignId('company_id')
                ->constrained('companies')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->string('user_global_id');
            $table->foreignId('finished_product_variant_id')
                ->constrained('product_variants')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreignId('location_to_id')
                ->constrained('locations')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreignId('uom_id')
                ->constrained('uoms')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->date('receipt_date');
            $table->decimal('quantity_good', 18, 6)->default(0);
            $table->decimal('quantity_scrap', 18, 6)->default(0);
            $table->decimal('total_material_cost', 18, 6)->default(0);
            $table->decimal('labor_cost', 18, 6)->default(0);
            $table->decimal('overhead_cost', 18, 6)->default(0);
            $table->decimal('total_cost', 18, 6)->default(0);
            $table->decimal('unit_cost', 18, 6)->default(0);
            $table->foreignId('inventory_transaction_id')
                ->nullable()
                ->constrained('inventory_transactions')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->foreignId('lot_id')
                ->nullable()
                ->constrained('lots')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->foreignId('serial_id')
                ->nullable()
                ->constrained('serials')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->string('posted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_global_id')
                ->references('global_id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreign('posted_by')
                ->references('global_id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->index(['company_id', 'branch_id'], 'idx_fgr_company_branch');
            $table->index(['work_order_id', 'receipt_date'], 'idx_fgr_wo_date');
            $table->index('status', 'idx_fgr_status');
            $table->index('inventory_transaction_id', 'idx_fgr_inv_txn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finished_goods_receipts');
    }
};