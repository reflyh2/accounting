<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE EXTENSION IF NOT EXISTS btree_gist");

        $dateColumns = [
            'bookings' => ['booked_at', 'held_until'],
            'booking_lines' => ['start_datetime', 'end_datetime'],
            'availability_rules' => ['start_datetime', 'end_datetime'],
            'occurrences' => ['start_datetime', 'end_datetime'],
        ];

        foreach ($dateColumns as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement(sprintf(
                    "ALTER TABLE %s ALTER COLUMN %s TYPE timestamptz USING %s AT TIME ZONE 'UTC'",
                    $table,
                    $column,
                    $column
                ));
            }
        }

        DB::statement("
            ALTER TABLE booking_lines
            DROP CONSTRAINT IF EXISTS booking_lines_instance_overlap
        ");

        DB::statement("
            ALTER TABLE booking_lines
            ADD CONSTRAINT booking_lines_instance_overlap
            EXCLUDE USING gist (
                resource_instance_id WITH =,
                tstzrange(start_datetime, end_datetime) WITH &&
            )
            WHERE (resource_instance_id IS NOT NULL)
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE booking_lines
            DROP CONSTRAINT IF EXISTS booking_lines_instance_overlap
        ");

        $dateColumns = [
            'bookings' => ['booked_at', 'held_until'],
            'booking_lines' => ['start_datetime', 'end_datetime'],
            'availability_rules' => ['start_datetime', 'end_datetime'],
            'occurrences' => ['start_datetime', 'end_datetime'],
        ];

        foreach ($dateColumns as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement(sprintf(
                    "ALTER TABLE %s ALTER COLUMN %s TYPE timestamp WITHOUT TIME ZONE USING %s AT TIME ZONE 'UTC'",
                    $table,
                    $column,
                    $column
                ));
            }
        }
    }
};

