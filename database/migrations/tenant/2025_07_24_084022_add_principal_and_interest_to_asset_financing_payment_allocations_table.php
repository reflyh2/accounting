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
        Schema::table('asset_financing_payment_allocations', function (Blueprint $table) {
            $table->decimal('principal_amount', 15, 2)->default(0)->after('allocated_amount');
            $table->decimal('interest_amount', 15, 2)->default(0)->after('principal_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_financing_payment_allocations', function (Blueprint $table) {
            $table->dropColumn(['principal_amount', 'interest_amount']);
        });
    }
};
