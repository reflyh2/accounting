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
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_id']);
            $table->dropColumn('purchase_order_id');

            $table->string('payment_method')->nullable()->after('currency_id');
            $table->foreignId('partner_bank_account_id')
                ->nullable()
                ->after('payment_method')
                ->constrained('partner_bank_accounts')
                ->nullOnDelete();
            $table->foreignId('inventory_transaction_id')
                ->nullable()
                ->after('partner_bank_account_id')
                ->constrained('inventory_transactions')
                ->nullOnDelete();
        });

        Schema::create('purchase_invoice_purchase_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')
                ->constrained('purchase_invoices')
                ->cascadeOnDelete();
            $table->foreignId('purchase_order_id')
                ->constrained('purchase_orders')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['purchase_invoice_id', 'purchase_order_id'], 'pi_po_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_purchase_order');

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropForeign(['inventory_transaction_id']);
            $table->dropForeign(['partner_bank_account_id']);
            $table->dropColumn(['inventory_transaction_id', 'partner_bank_account_id', 'payment_method']);
            
            $table->foreignId('purchase_order_id')
                ->nullable()
                ->after('partner_id')
                ->constrained('purchase_orders')
                ->nullOnDelete();
        });
    }
};
