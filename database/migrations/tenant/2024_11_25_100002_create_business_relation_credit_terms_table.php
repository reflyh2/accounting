<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('business_relation_credit_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_relation_id')->constrained()->onDelete('cascade');
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('used_credit', 15, 2)->default(0);
            $table->integer('payment_term_days')->default(0);
            $table->string('payment_term_type')->default('net'); // net, cod, prepaid
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('business_relation_credit_terms');
    }
}; 