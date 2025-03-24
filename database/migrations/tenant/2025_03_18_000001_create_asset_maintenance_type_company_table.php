<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('asset_maintenance_type_company', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_maintenance_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['asset_maintenance_type_id', 'company_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('asset_maintenance_type_company');
    }
}; 