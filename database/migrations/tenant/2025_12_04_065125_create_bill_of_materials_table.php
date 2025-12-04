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
        Schema::create('bill_of_materials', function (Blueprint $table) {
            $table->id();
            $table->string('bom_number')->unique();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('branch_id')->constrained('branches')->onUpdate('cascade')->onDelete('restrict');
            $table->string('user_global_id');
            $table->foreignId('finished_product_id')->constrained('products')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('finished_product_variant_id')->nullable()->constrained('product_variants')->onUpdate('cascade')->onDelete('restrict');
            $table->decimal('finished_quantity', 18, 3);
            $table->foreignId('finished_uom_id')->constrained('uoms')->onUpdate('cascade')->onDelete('restrict');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('version', 20)->default('1.0');
            $table->string('status')->default('draft'); // draft, active, inactive
            $table->boolean('is_default')->default(false);
            $table->date('effective_date')->nullable();
            $table->date('expiration_date')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();

            $table->index(['company_id', 'finished_product_id'], 'idx_bom_company_product');
            $table->index(['branch_id', 'status'], 'idx_bom_branch_status');
            $table->index('bom_number', 'idx_bom_number');

            $table->foreign('user_global_id')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('deleted_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_of_materials');
    }
};
