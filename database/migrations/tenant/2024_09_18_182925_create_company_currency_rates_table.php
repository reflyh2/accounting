<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCompanyCurrencyRatesTable extends Migration
{
    public function up()
    {
        Schema::create('company_currency_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('currency_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->decimal('exchange_rate', 10, 4);
            $table->timestamps();

            $table->unique(['company_id', 'currency_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('company_currency_rates');
    }
}