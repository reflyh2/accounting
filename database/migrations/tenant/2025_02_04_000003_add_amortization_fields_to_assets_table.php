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
        Schema::table('assets', function (Blueprint $table) {
            $table->integer('amortization_term_months')->nullable()->after('rental_amount');
            $table->date('first_amortization_date')->nullable()->after('amortization_term_months');
            $table->decimal('accumulated_amortization', 15, 2)->nullable()->after('first_amortization_date');
            $table->date('last_amortization_date')->nullable()->after('accumulated_amortization');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn([
                'amortization_term_months',
                'first_amortization_date',
                'accumulated_amortization',
                'last_amortization_date',
            ]);
        });
    }
}; 