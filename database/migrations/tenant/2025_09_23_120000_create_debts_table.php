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
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            // Basic identification
            $table->string('type'); // payable | receivable
            $table->string('number')->unique();

            // Organizational context
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');

            // Currency context
            $table->foreignId('currency_id')->constrained();
            $table->decimal('exchange_rate', 15, 6)->default(1);

            // Dates
            $table->date('issue_date');
            $table->date('due_date')->nullable();

            // Amounts
            $table->decimal('amount', 15, 2); // amount in transaction currency
            $table->decimal('primary_currency_amount', 15, 2)->default(0); // amount in primary currency

            // Status and references
            $table->string('status')->default('open'); // open, partially_paid, paid, overdue, cancelled, closed, defaulted
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();

            // Optional link to auto-generated journal
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
        Schema::dropIfExists('debts');
    }
};


