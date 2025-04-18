<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('asset_category_company', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_category_id')->constrained()->onDelete('cascade');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('asset_account_id')->nullable()->constrained('accounts')->onDelete('cascade');
            $table->foreignId('asset_depreciation_account_id')->nullable()->constrained('accounts')->onDelete('cascade');
            $table->foreignId('asset_accumulated_depreciation_account_id')->nullable()->constrained('accounts')->onDelete('cascade');
            $table->foreignId('asset_amortization_account_id')->nullable()->constrained('accounts')->onDelete('cascade');
            $table->foreignId('asset_prepaid_amortization_account_id')->nullable()->constrained('accounts')->onDelete('cascade');
            $table->foreignId('asset_rental_cost_account_id')->nullable()->constrained('accounts')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['asset_category_id', 'company_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('asset_category_company');
    }
}; 