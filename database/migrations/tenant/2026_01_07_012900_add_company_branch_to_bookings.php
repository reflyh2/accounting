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
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')
                  ->constrained('companies')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('company_id')
                  ->constrained('branches')->nullOnDelete();

            $table->index(['company_id', 'branch_id', 'status'], 'idx_booking_company_branch_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_booking_company_branch_status');
            $table->dropForeign(['company_id']);
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['company_id', 'branch_id']);
        });
    }
};
