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
        Schema::create('asset_financing_agreements', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->date('agreement_date');
            $table->foreignId('creditor_id')->constrained('partners')->onDelete('cascade');
            $table->foreignId('asset_invoice_id')->constrained()->onDelete('cascade');
            $table->decimal('total_amount', 15, 2);
            $table->decimal('interest_rate', 5, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('payment_frequency'); // monthly, quarterly, annually
            $table->string('status'); // pending, active, closed, defaulted, cancelled
            $table->string('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_financing_agreements');
    }
};
