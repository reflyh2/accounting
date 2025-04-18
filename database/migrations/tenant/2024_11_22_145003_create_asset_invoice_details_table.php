<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('asset_rental_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('asset_id')->constrained()->onDelete('cascade');
            $table->text('description')->nullable();
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('line_amount', 15, 2);
            $table->date('lease_rental_start_date')->nullable();
            $table->date('lease_rental_end_date')->nullable();
            $table->string('billing_frequency')->nullable(); // daily, weekly, monthly, quarterly, yearly, bulk]
            $table->decimal('total_lease_rental_amount', 15, 2)->nullable();
            $table->decimal('periodic_rate', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('asset_rental_payments');
    }
}; 