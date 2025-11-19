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
        Schema::create('external_debt_payments', function (Blueprint $table) {
            $table->id();

            // Identification
            $table->string('number')->unique();

            // Link to the debt (determines payable vs receivable via external_debts.type)
            $table->foreignId('external_debt_id')->constrained('external_debts')->onDelete('cascade');

            // Organizational context (duplicated for efficient filtering)
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');

            // Currency context
            $table->foreignId('currency_id')->constrained();
            $table->decimal('exchange_rate', 15, 6)->default(1);

            // Dates
            $table->date('payment_date');

            // Amounts
            $table->decimal('amount', 15, 2); // payment amount in transaction currency
            $table->decimal('primary_currency_amount', 15, 2)->default(0); // amount in primary currency

            // Optional references
            $table->string('payment_method')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();

            // Optional linkage to a generated journal
            $table->foreignId('journal_id')->nullable()->constrained('journals')->onUpdate('cascade')->onDelete('cascade');

            // Audit
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->softDeletes();

            // Foreign keys for audit fields
            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_debt_payments');
    }
};


