<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('asset_invoices', function (Blueprint $table) {
            $table->id();
            $table->date('invoice_date');
            $table->string('type'); // purchase, lease, rental, sales
            $table->string('number')->unique();
            $table->foreignId('partner_id')->constrained()->onDelete('cascade');
            $table->date('due_date');
            $table->decimal('total_amount', 15, 2);
            $table->string('status')->default('open'); // open, paid, overdue, cancelled, voided, closed, partially_paid
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->softDeletes();

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::dropIfExists('asset_invoices');
    }
}; 