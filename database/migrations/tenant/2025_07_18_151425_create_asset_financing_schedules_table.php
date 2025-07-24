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
        Schema::create('asset_financing_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_financing_agreement_id')->constrained()->onDelete('cascade');
            $table->integer('payment_number');
            $table->date('payment_date');
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_amount', 15, 2);
            $table->decimal('total_payment', 15, 2);
            $table->string('status')->default('unpaid'); // unpaid, paid, overdue
            $table->date('paid_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_financing_schedules');
    }
};
