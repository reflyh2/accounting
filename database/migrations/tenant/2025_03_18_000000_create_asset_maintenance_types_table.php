<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('asset_maintenance_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('asset_category_id')->constrained('asset_categories');
            $table->foreignId('maintenance_cost_account_id')->nullable()->constrained('accounts');
            $table->string('description')->nullable();
            $table->string('maintenance_interval')->nullable()->comment('Recommended interval (e.g., "30 days", "6 months")');
            $table->integer('maintenance_interval_days')->nullable()->comment('Interval converted to days for calculation');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('asset_maintenance_types');
    }
}; 