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
        Schema::table('asset_invoices', function (Blueprint $table) {
            $table->foreignId('currency_id')->after('branch_id')->nullable()->constrained()->onDelete('restrict');
            $table->decimal('exchange_rate', 15, 6)->default(1)->after('currency_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_invoices', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
            $table->dropColumn(['currency_id', 'exchange_rate']);
        });
    }
};
