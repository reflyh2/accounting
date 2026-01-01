<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->foreignId('external_debt_id')
                ->nullable()
                ->after('inventory_transaction_id')
                ->constrained('external_debts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropForeign(['external_debt_id']);
            $table->dropColumn('external_debt_id');
        });
    }
};
