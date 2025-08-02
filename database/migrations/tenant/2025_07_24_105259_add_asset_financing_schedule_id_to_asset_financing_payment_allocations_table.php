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
            $table->foreignId('asset_financing_schedule_id')->nullable()->after('asset_financing_agreement_id')->constrained()->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_financing_payment_allocations', function (Blueprint $table) {
            $table->dropForeign(['asset_financing_schedule_id']);
            $table->dropColumn('asset_financing_schedule_id');
        });
    }
};
