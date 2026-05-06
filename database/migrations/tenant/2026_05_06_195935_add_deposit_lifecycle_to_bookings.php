<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Snapshot of deposit_amount at the moment the receive event was dispatched.
            // Tracking it separately lets us guard against double-firing and lets us
            // know how much to apply to the invoice even if deposit_amount is later edited.
            $table->decimal('deposit_received_amount', 18, 2)->nullable()->after('deposit_amount');
            $table->timestamp('deposit_received_at')->nullable()->after('deposit_received_amount');
            $table->timestamp('deposit_applied_at')->nullable()->after('deposit_received_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['deposit_received_amount', 'deposit_received_at', 'deposit_applied_at']);
        });
    }
};
