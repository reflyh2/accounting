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
        Schema::create('sales_delivery_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_delivery_id')->constrained('sales_deliveries')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('cost_item_id')->nullable()->constrained('cost_items')->onUpdate('cascade')->onDelete('restrict');
            $table->text('description')->nullable();
            $table->decimal('amount', 18, 2);
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->onUpdate('cascade')->onDelete('restrict');
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->timestamps();

            $table->index('sales_delivery_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_delivery_costs');
    }
};
