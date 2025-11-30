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
        Schema::create('internal_debt_payments', function (Blueprint $table) {
            $table->id();

            // payable | receivable
            $table->string('type');

            // Identification
            $table->string('number')->unique();

            // Organizational context
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');

            // Currency context
            $table->foreignId('currency_id')->constrained();
            $table->decimal('exchange_rate', 15, 6)->default(1);

            // Dates
            $table->date('payment_date');

            // Payment meta
            $table->foreignId('account_id')->constrained('accounts')->onDelete('restrict');
            $table->string('payment_method')->nullable();
            $table->string('reference_number')->nullable();

            // Amounts (master totals)
            $table->decimal('amount', 15, 2);
            $table->decimal('primary_currency_amount', 15, 2)->default(0);

            // Optional linkage to a generated journal
            $table->foreignId('journal_id')->nullable()->constrained('journals')->onUpdate('cascade')->onDelete('cascade');

            // Audit
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
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
        Schema::dropIfExists('internal_debt_payments');
    }
};


