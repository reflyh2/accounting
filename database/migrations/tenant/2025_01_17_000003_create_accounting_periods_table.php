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
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');                       // e.g., 'Jan 2025', 'Q1 2025'
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('open');    // open, soft_closed, closed
            $table->string('closed_by')->nullable();      // user who closed the period
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'start_date', 'end_date']);
            $table->foreign('closed_by')->references('global_id')->on('users');
            $table->index(['company_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};
