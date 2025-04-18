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
        Schema::create('asset_financing_payments', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->date('payment_date');
            $table->foreignId('creditor_id')->constrained('partners')->onDelete('cascade');
            $table->string('reference')->nullable();
            $table->decimal('total_paid_amount', 15, 2);
            $table->string('payment_method'); // cash, check, credit_card, bank_transfer, other
            $table->string('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_financing_payments');
    }
};
