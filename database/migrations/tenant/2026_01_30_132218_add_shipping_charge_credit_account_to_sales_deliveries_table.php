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
        Schema::table('sales_deliveries', function (Blueprint $table) {
            $table->foreignId('shipping_charge_credit_account_id')
                ->nullable()
                ->after('actual_shipping_charge')
                ->constrained('accounts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_charge_credit_account_id');
        });
    }
};
