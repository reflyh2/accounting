<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('asset_financing_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->onDelete('cascade');
            $table->date('due_date');
            $table->date('payment_date')->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('principal_portion', 15, 2);
            $table->decimal('interest_portion', 15, 2);
            $table->string('status')->default('pending'); // pending, paid, overdue
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('asset_financing_payments');
    }
}; 