<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoice_costs', function (Blueprint $table) {
            // Captures which supplier the cost is owed to so it can be billed
            // back through Option 1 (PI generation) or consumed via Option 2
            // (supplier deposit). Required only when the linked CostItem has
            // is_supplier_payable = true; controller enforces.
            $table->foreignId('supplier_partner_id')->nullable()
                ->after('cost_item_id')
                ->constrained('partners')->cascadeOnUpdate()->nullOnDelete();

            $table->nullableMorphs('settled_by');
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoice_costs', function (Blueprint $table) {
            $table->dropMorphs('settled_by');
            $table->dropForeign(['supplier_partner_id']);
            $table->dropColumn('supplier_partner_id');
        });
    }
};
