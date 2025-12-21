<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_discount_limits', function (Blueprint $table) {
            $table->id();
            $table->string('user_global_id');
            $table->foreignId('product_id')->nullable()->constrained('products')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('product_category_id')->nullable()->constrained('product_categories')->onUpdate('cascade')->onDelete('cascade');
            // If both product_id and product_category_id are null = global limit
            $table->decimal('max_discount_percent', 5, 2);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->foreign('user_global_id')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');

            $table->unique(['user_global_id', 'product_id', 'product_category_id'], 'uq_user_discount_scope');
            $table->index('user_global_id', 'idx_user_discount_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_discount_limits');
    }
};
