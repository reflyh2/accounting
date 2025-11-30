<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_debt_payment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_debt_payment_id')->constrained('internal_debt_payments')->onDelete('cascade');
            $table->foreignId('internal_debt_id')->constrained('internal_debts')->onDelete('restrict');
            $table->decimal('amount', 15, 2);
            $table->decimal('primary_currency_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_debt_payment_details');
    }
};


