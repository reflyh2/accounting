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
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_address_id')->nullable()->after('partner_id');
            $table->unsignedBigInteger('invoice_address_id')->nullable()->after('shipping_address_id');

            $table->foreign('shipping_address_id')->references('id')->on('partner_addresses')->onDelete('restrict');
            $table->foreign('invoice_address_id')->references('id')->on('partner_addresses')->onDelete('restrict');
        });

        Schema::table('sales_deliveries', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_address_id')->nullable()->after('partner_id');
            $table->unsignedBigInteger('invoice_address_id')->nullable()->after('shipping_address_id');

            $table->foreign('shipping_address_id')->references('id')->on('partner_addresses')->onDelete('restrict');
            $table->foreign('invoice_address_id')->references('id')->on('partner_addresses')->onDelete('restrict');
        });

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_address_id')->nullable()->after('partner_id');
            $table->unsignedBigInteger('invoice_address_id')->nullable()->after('shipping_address_id');

            $table->foreign('shipping_address_id')->references('id')->on('partner_addresses')->onDelete('restrict');
            $table->foreign('invoice_address_id')->references('id')->on('partner_addresses')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['shipping_address_id']);
            $table->dropForeign(['invoice_address_id']);
            $table->dropColumn(['shipping_address_id', 'invoice_address_id']);
        });

        Schema::table('sales_deliveries', function (Blueprint $table) {
            $table->dropForeign(['shipping_address_id']);
            $table->dropForeign(['invoice_address_id']);
            $table->dropColumn(['shipping_address_id', 'invoice_address_id']);
        });

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropForeign(['shipping_address_id']);
            $table->dropForeign(['invoice_address_id']);
            $table->dropColumn(['shipping_address_id', 'invoice_address_id']);
        });
    }
};
