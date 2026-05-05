<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('booking_subtype', 40)->nullable()->after('booking_type');
        });

        Schema::table('booking_lines', function (Blueprint $table) {
            $table->jsonb('meta')->default(DB::raw("'{}'::jsonb"))->after('resource_instance_id');
        });

        DB::statement('CREATE INDEX IF NOT EXISTS idx_booking_lines_meta_gin ON booking_lines USING GIN (meta)');

        // Backfill subtype from legacy booking_type
        DB::table('bookings')->whereNull('booking_subtype')->update([
            'booking_subtype' => DB::raw('booking_type'),
        ]);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_booking_lines_meta_gin');

        Schema::table('booking_lines', function (Blueprint $table) {
            $table->dropColumn('meta');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('booking_subtype');
        });
    }
};
