<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('fulfillment_mode', 30)->nullable()->after('cost_model');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('fulfillment_mode', 30)->default('self_operated')->after('booking_type');
            $table->index(['company_id', 'fulfillment_mode', 'status'], 'idx_booking_company_mode_status');
        });

        DB::table('bookings')->update(['fulfillment_mode' => 'self_operated']);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_booking_company_mode_status');
            $table->dropColumn('fulfillment_mode');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('fulfillment_mode');
        });
    }
};
