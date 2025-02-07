<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('assets', function (Blueprint $table) {
            // Add first payment date for financed purchases
            $table->date('first_payment_date')->nullable()->after('financing_term_months');
            
            // Add first depreciation date for depreciable assets
            $table->date('first_depreciation_date')->nullable()->after('salvage_value');
            
            // Make payment_frequency only applicable for periodic rentals
            // First modify any existing data
            DB::statement("UPDATE assets SET payment_frequency = NULL WHERE acquisition_type != 'periodic_rental'");
        });
    }

    public function down()
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn([
                'first_payment_date',
                'first_depreciation_date',
            ]);
        });
    }
}; 