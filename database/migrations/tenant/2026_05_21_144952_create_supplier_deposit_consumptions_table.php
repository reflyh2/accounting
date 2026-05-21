<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_deposit_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_deposit_id')->constrained('supplier_deposits')->cascadeOnUpdate()->restrictOnDelete();
            // Polymorphic: the cost source row that consumed the deposit
            // (BookingLine or SalesInvoiceCost). One row per draw-down.
            $table->morphs('consumed_by');
            $table->decimal('amount', 18, 2);
            $table->decimal('amount_base', 18, 4);
            $table->timestamp('consumed_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->string('created_by')->nullable();

            $table->index(['supplier_deposit_id', 'consumed_at'], 'idx_supplier_consumption_deposit');

            $table->foreign('created_by')->references('global_id')->on('users')->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_deposit_consumptions');
    }
};
