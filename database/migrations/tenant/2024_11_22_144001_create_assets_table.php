<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('category_id')->constrained('asset_categories');
            $table->string('serial_number')->nullable();
            $table->string('status')->default('active');
            $table->decimal('purchase_cost', 15, 2)->nullable();
            $table->date('purchase_date')->nullable();
            $table->string('supplier')->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->string('depreciation_method')->default('straight-line');
            $table->integer('useful_life_months')->nullable();
            $table->decimal('salvage_value', 15, 2)->nullable()->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('assets');
    }
}; 