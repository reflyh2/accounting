<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('business_relation_company', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_relation_id')->constrained()->onDelete('cascade');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Ensure unique combination of business_relation and company
            $table->unique(['business_relation_id', 'company_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('business_relation_company');
    }
}; 