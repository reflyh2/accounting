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
        Schema::table('component_issue_lines', function (Blueprint $table) {
            // Drop old string columns
            $table->dropColumn(['lot_number', 'serial_number']);
        });

        Schema::table('component_issue_lines', function (Blueprint $table) {
            // Add foreign key columns
            $table->foreignId('lot_id')->nullable()->after('uom_id')->constrained('lots')->onDelete('set null');
            $table->foreignId('serial_id')->nullable()->after('lot_id')->constrained('serials')->onDelete('set null');
            $table->index('lot_id');
            $table->index('serial_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('component_issue_lines', function (Blueprint $table) {
            $table->dropForeign(['lot_id']);
            $table->dropForeign(['serial_id']);
            $table->dropIndex(['lot_id']);
            $table->dropIndex(['serial_id']);
            $table->dropColumn(['lot_id', 'serial_id']);
        });

        Schema::table('component_issue_lines', function (Blueprint $table) {
            $table->string('lot_number')->nullable()->after('uom_id');
            $table->string('serial_number')->nullable()->after('lot_number');
        });
    }
};
