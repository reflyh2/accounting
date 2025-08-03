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
        Schema::table('asset_financing_agreements', function (Blueprint $table) {
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->onUpdate('cascade')->onDelete('restrict');
            $table->decimal('exchange_rate', 15, 2)->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_financing_agreements', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
            $table->dropColumn('currency_id');
            $table->dropColumn('exchange_rate');
        });
    }
};
