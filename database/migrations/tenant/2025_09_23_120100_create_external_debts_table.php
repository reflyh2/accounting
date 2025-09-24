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
        Schema::create('external_debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_id')->constrained('debts')->onDelete('cascade');
            $table->foreignId('partner_id')->constrained()->onDelete('cascade');
            // Optional linkage to a source document, like asset invoice, sales invoice, etc.
            $table->string('source_type')->nullable(); // morph type if needed in future
            $table->unsignedBigInteger('source_id')->nullable(); // source record id
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_debts');
    }
};


