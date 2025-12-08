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
        Schema::create('component_issue_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_issue_id')->constrained('component_issues')->onDelete('cascade');
            $table->integer('line_number');
            $table->foreignId('bom_line_id')->nullable()->constrained('bill_of_material_lines')->onDelete('set null');
            $table->foreignId('component_product_id')->constrained('products')->onDelete('restrict');
            $table->foreignId('component_product_variant_id')->nullable()->constrained('product_variants')->onDelete('restrict');
            $table->decimal('quantity_issued', 15, 6);
            $table->foreignId('uom_id')->constrained('uoms')->onDelete('restrict');
            $table->string('lot_number')->nullable();
            $table->string('serial_number')->nullable();
            $table->boolean('backflush')->default(false);
            $table->decimal('unit_cost', 15, 6)->default(0);
            $table->decimal('total_cost', 15, 6)->default(0);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['component_issue_id', 'line_number'], 'idx_ci_line_issue_number');
            $table->index('component_product_id', 'idx_ci_line_component');
            $table->index('bom_line_id', 'idx_ci_line_bom');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('component_issue_lines');
    }
};
