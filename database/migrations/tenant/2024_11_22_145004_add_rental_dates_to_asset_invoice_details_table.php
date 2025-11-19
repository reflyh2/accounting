<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('asset_invoice_details', function (Blueprint $table) {
            $table->date('rental_start_date')->nullable()->after('line_amount');
            $table->date('rental_end_date')->nullable()->after('rental_start_date');
        });
    }

    public function down()
    {
        Schema::table('asset_invoice_details', function (Blueprint $table) {
            $table->dropColumn(['rental_start_date', 'rental_end_date']);
        });
    }
}; 