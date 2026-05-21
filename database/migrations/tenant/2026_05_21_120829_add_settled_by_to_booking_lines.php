<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_lines', function (Blueprint $table) {
            // Polymorphic settlement marker. Points at a PurchaseInvoiceLine
            // (Option 1: generated PI) OR a SupplierDepositConsumption
            // (Option 2: deposit auto-consumption). NULL = obligation still
            // outstanding and eligible for either path.
            $table->nullableMorphs('settled_by');
        });
    }

    public function down(): void
    {
        Schema::table('booking_lines', function (Blueprint $table) {
            $table->dropMorphs('settled_by');
        });
    }
};
