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
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('asset_category_id')->constrained('asset_categories');
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type')->default('tangible'); // tangible, intangible
            $table->string('acquisition_type')->default('outright_purchase'); // outright_purchase, financed_purchase, leased, rented
            $table->date('acquisition_date')->nullable();
            $table->decimal('cost_basis', 15, 2)->nullable();
            $table->decimal('salvage_value', 15, 2)->nullable()->default(0);
            $table->boolean('is_depreciable')->default(true);
            $table->boolean('is_amortizable')->default(false);
            $table->string('depreciation_method')->default('straight-line'); // For depreciation and amortization: straight-line, declining-balance, units-of-production, sum-of-years-digits, no-depreciation
            $table->integer('useful_life_months')->nullable(); // For depreciation and amortization
            $table->date('depreciation_start_date')->nullable(); // For depreciation and amortization
            $table->decimal('accumulated_depreciation', 15, 2)->default(0); // For depreciation and amortization
            $table->decimal('net_book_value', 15, 2)->default(0); // For depreciation and amortization
            $table->string('status')->default('active'); // active, inactive, disposed, sold, scrapped, written_off
            $table->text('notes')->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->softDeletes();

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::dropIfExists('assets');
    }
}; 