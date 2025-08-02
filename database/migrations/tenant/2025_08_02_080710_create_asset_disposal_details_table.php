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
        Schema::create('asset_disposal_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_disposal_id')->constrained('asset_disposals')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('asset_id')->constrained('assets')->onUpdate('cascade')->onDelete('restrict');
            $table->decimal('carrying_amount', 15, 2)->default(0);
            $table->decimal('proceeds_amount', 15, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_disposal_details');
    }
};
