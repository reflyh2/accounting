<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('asset_maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets');
            $table->date('maintenance_date');
            $table->string('maintenance_type');
            $table->decimal('cost', 15, 2);
            $table->text('description');
            $table->string('performed_by')->nullable();
            $table->date('next_maintenance_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('asset_maintenance_records');
    }
}; 