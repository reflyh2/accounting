<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('partner_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->onDelete('cascade');
            $table->string('role'); // supplier, customer, asset_supplier, asset_customer, creditor, others
            $table->string('status')->default('active'); // active, inactive
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('used_credit', 15, 2)->default(0);
            $table->integer('payment_term_days')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('partner_roles');
    }
}; 