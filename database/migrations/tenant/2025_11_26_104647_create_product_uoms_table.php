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
        Schema::create('product_uoms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('uom_id')->constrained('uoms')->onUpdate('cascade')->onDelete('restrict');
            $table->boolean('is_base')->default(false);
            $table->unique(['product_id', 'variant_id', 'uom_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_uoms');
    }
};
