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
        Schema::create('component_scraps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')
                ->constrained('work_orders')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreignId('component_issue_line_id')
                ->nullable()
                ->constrained('component_issue_lines')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->foreignId('bom_line_id')
                ->nullable()
                ->constrained('bill_of_material_lines')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->foreignId('component_product_id')
                ->constrained('products')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreignId('component_product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->decimal('scrap_quantity', 15, 6);
            $table->foreignId('uom_id')
                ->constrained('uoms')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->string('scrap_reason', 255);
            $table->text('notes')->nullable();
            $table->boolean('is_backflush')->default(false);
            $table->foreignId('finished_goods_receipt_id')
                ->nullable()
                ->constrained('finished_goods_receipts')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->string('user_global_id');
            $table->date('scrap_date');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_global_id')
                ->references('global_id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index(['work_order_id', 'scrap_date'], 'idx_cs_wo_date');
            $table->index('component_issue_line_id', 'idx_cs_ci_line');
            $table->index('finished_goods_receipt_id', 'idx_cs_fgr');
            $table->index('is_backflush', 'idx_cs_backflush');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('component_scraps');
    }
};
