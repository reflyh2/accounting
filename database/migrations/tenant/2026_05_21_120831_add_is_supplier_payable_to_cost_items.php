<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_items', function (Blueprint $table) {
            // When true, a SalesInvoiceCost referencing this item requires a
            // supplier_partner_id and the obligation becomes eligible for
            // PI generation (Option 1) or supplier-deposit consumption
            // (Option 2). Internal costs (depreciation, payroll allocations,
            // etc.) leave this false and bypass both flows entirely.
            $table->boolean('is_supplier_payable')->default(false)->after('credit_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('cost_items', function (Blueprint $table) {
            $table->dropColumn('is_supplier_payable');
        });
    }
};
