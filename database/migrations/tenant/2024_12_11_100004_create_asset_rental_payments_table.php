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
            $table->foreignId('asset_id')->constrained()->onDelete('cascade');;
            $table->date('payment_date')->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('status')->default('pending'); // pending, paid, overdue
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('asset_rental_payments');
    }
}; 