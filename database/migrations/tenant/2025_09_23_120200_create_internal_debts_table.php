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
        Schema::create('internal_debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_id')->constrained('debts')->onDelete('cascade');

            // Internal counterparty: either another branch in same tenant/company context
            $table->foreignId('counterparty_branch_id')->constrained('branches')->onDelete('cascade');

            // Optional linkage to intercompany/company if modeled; keep nullable for flexibility
            $table->unsignedBigInteger('counterparty_company_id')->nullable();

            // Optional linkage to a source document (transfer, recharge, settlement, etc.)
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internal_debts');
    }
};


