<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoice_lines', function (Blueprint $table) {
            // Debit-account override for obligation-style PI lines.
            // When the PI line was generated from a BookingLine or
            // SalesInvoiceCost via ObligationBillingService, posting
            // debits this account directly (the supplier_clearing,
            // supplier_payable_passthrough, or CostItem.credit_account
            // that needs to be drained) instead of inferring from a
            // product variant. NULL for normal goods PI lines.
            $table->foreignId('account_id')->nullable()
                ->after('uom_id')
                ->constrained('accounts')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoice_lines', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
        });
    }
};
