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
        Schema::create('gl_event_configuration_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gl_event_configuration_id')->constrained('gl_event_configurations')->onUpdate('cascade')->onDelete('cascade');
            $table->string('role', 100);
            $table->enum('direction', ['debit', 'credit']);
            $table->foreignId('account_id')->constrained('accounts')->onUpdate('cascade')->onDelete('restrict');
            $table->timestamps();

            $table->index(['gl_event_configuration_id', 'role', 'direction']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gl_event_configuration_lines');
    }
};
