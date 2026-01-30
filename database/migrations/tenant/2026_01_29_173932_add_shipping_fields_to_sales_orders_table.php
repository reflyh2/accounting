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
            $table->string('shipping_type', 30)->nullable()->after('invoice_address_id');
            $table->foreignId('shipping_provider_id')->nullable()->after('shipping_type')
                ->constrained('shipping_providers')->onUpdate('cascade')->onDelete('restrict');
            $table->decimal('estimated_shipping_charge', 18, 2)->default(0)->after('shipping_provider_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['shipping_provider_id']);
            $table->dropColumn(['shipping_type', 'shipping_provider_id', 'estimated_shipping_charge']);
        });
    }
};
