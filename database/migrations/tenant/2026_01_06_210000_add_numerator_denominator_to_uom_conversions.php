<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add numerator and denominator columns to uom_conversions table
     * to eliminate floating-point rounding errors in conversions.
     * 
     * Example: 1 dozen = 12 pcs → numerator=12, denominator=1
     * Example: 1 pcs = 1/12 dozen → numerator=1, denominator=12
     */
    public function up(): void
    {
        Schema::table('uom_conversions', function (Blueprint $table) {
            $table->integer('numerator')->default(1)->after('to_uom_id');
            $table->integer('denominator')->default(1)->after('numerator');
        });
    }

    public function down(): void
    {
        Schema::table('uom_conversions', function (Blueprint $table) {
            $table->dropColumn(['numerator', 'denominator']);
        });
    }
};
