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
        Schema::table('asset_financing_schedules', function (Blueprint $table) {
            $table->decimal('paid_principal_amount', 15, 2)->default(0)->after('total_payment');
            $table->decimal('paid_interest_amount', 15, 2)->default(0)->after('paid_principal_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_financing_schedules', function (Blueprint $table) {
            $table->dropColumn(['paid_principal_amount', 'paid_interest_amount']);
        });
    }
};
