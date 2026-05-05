<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_lines', function (Blueprint $table) {
            $table->foreignId('supplier_partner_id')->nullable()->after('product_variant_id')
                ->constrained('partners')->nullOnDelete();
            $table->decimal('supplier_cost', 18, 2)->nullable()->after('amount');
            $table->decimal('supplier_cost_base', 18, 4)->nullable()->after('supplier_cost');
            $table->decimal('commission_amount', 18, 2)->nullable()->after('supplier_cost_base');
            $table->decimal('passthrough_amount', 18, 2)->nullable()->after('commission_amount');
            $table->string('supplier_invoice_ref', 120)->nullable()->after('passthrough_amount');

            $table->index('supplier_partner_id', 'idx_booking_line_supplier_partner');
        });
    }

    public function down(): void
    {
        Schema::table('booking_lines', function (Blueprint $table) {
            $table->dropIndex('idx_booking_line_supplier_partner');
            $table->dropForeign(['supplier_partner_id']);
            $table->dropColumn([
                'supplier_partner_id',
                'supplier_cost',
                'supplier_cost_base',
                'commission_amount',
                'passthrough_amount',
                'supplier_invoice_ref',
            ]);
        });
    }
};
