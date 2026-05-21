<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoice_lines', function (Blueprint $table) {
            // Polymorphic pointer back to the obligation row the PI line was
            // generated from. For Option 1 PI generation, points at a
            // BookingLine or SalesInvoiceCost so we can:
            //   - reverse settled_by on the source when the PI is unposted
            //   - dedupe the outstanding-obligations list to skip already-billed rows
            // Normal manual PI lines leave this NULL and behave unchanged.
            $table->nullableMorphs('source');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoice_lines', function (Blueprint $table) {
            $table->dropMorphs('source');
        });
    }
};
