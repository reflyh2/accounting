<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_deposits', function (Blueprint $table) {
            $table->id();
            $table->string('deposit_number', 50)->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->date('deposit_date');
            $table->decimal('amount', 18, 2);
            // Running balance, decremented as consumptions happen.
            $table->decimal('balance', 18, 2);
            // Snapshot of what the cash leg debited and the asset leg credited
            // when the deposit was recorded. Used by refund + auditing.
            $table->foreignId('advance_account_id')->constrained('accounts')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('payment_account_id')->nullable()->constrained('accounts')->cascadeOnUpdate()->nullOnDelete();
            $table->string('payment_method', 30)->nullable();
            $table->foreignId('company_bank_account_id')->nullable()->constrained('company_bank_accounts')->cascadeOnUpdate()->nullOnDelete();
            // open = balance > 0, exhausted = balance = 0, refunded = balance forced to 0 via refund.
            $table->string('status', 20)->default('open');
            $table->timestamp('refunded_at')->nullable();
            $table->decimal('refunded_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->index(['company_id', 'partner_id', 'status'], 'idx_supplier_deposit_partner_status');
            $table->index('deposit_date', 'idx_supplier_deposit_date');

            $table->foreign('created_by')->references('global_id')->on('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('updated_by')->references('global_id')->on('users')->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_deposits');
    }
};
