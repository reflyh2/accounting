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
        Schema::create('bill_of_material_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_of_material_id')->constrained('bill_of_materials')->onUpdate('cascade')->onDelete('cascade');
            $table->integer('line_number');
            $table->foreignId('component_product_id')->constrained('products')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('component_product_variant_id')->nullable()->constrained('product_variants')->onUpdate('cascade')->onDelete('restrict');
            $table->decimal('quantity_per', 18, 3);
            $table->foreignId('uom_id')->constrained('uoms')->onUpdate('cascade')->onDelete('restrict');
            $table->decimal('scrap_percentage', 5, 2)->default(0); // percentage of scrap/waste
            $table->boolean('backflush')->default(false); // auto-issue at completion
            $table->string('operation')->nullable(); // manufacturing operation/step
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->index(['bill_of_material_id', 'line_number'], 'idx_bom_line_bom_number');
            $table->index('component_product_id', 'idx_bom_line_component');

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_of_material_lines');
    }
};
