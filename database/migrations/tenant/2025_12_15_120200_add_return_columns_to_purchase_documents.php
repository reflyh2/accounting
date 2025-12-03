<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->decimal('quantity_returned', 18, 3)
                ->default(0)
                ->after('quantity_invoiced');
            $table->decimal('quantity_returned_base', 18, 3)
                ->default(0)
                ->after('quantity_invoiced_base');
            $table->decimal('amount_returned', 18, 2)
                ->default(0)
                ->after('amount_invoiced');
        });

        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->decimal('quantity_returned', 18, 3)
                ->default(0)
                ->after('quantity_invoiced');
            $table->decimal('quantity_returned_base', 18, 3)
                ->default(0)
                ->after('quantity_invoiced_base');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->dropColumn([
                'quantity_returned',
                'quantity_returned_base',
                'amount_returned',
            ]);
        });

        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropColumn([
                'quantity_returned',
                'quantity_returned_base',
            ]);
        });
    }
};


