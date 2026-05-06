<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('deposit_applied_to_invoice_id')->nullable()
                ->after('deposit_applied_at')
                ->constrained('sales_invoices')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['deposit_applied_to_invoice_id']);
            $table->dropColumn('deposit_applied_to_invoice_id');
        });
    }
};
