<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnUpdate()->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('payment_method', 30)->nullable();
            $table->foreignId('company_bank_account_id')->nullable()->constrained('company_bank_accounts')->cascadeOnUpdate()->nullOnDelete();
            $table->dateTime('received_at');
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('global_id')->on('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('updated_by')->references('global_id')->on('users')->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_deposits');
    }
};
