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
        Schema::table('asset_disposals', function (Blueprint $table) {
            $table->foreignId('proceed_account_id')->nullable()->constrained('accounts')->onUpdate('cascade')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_disposals', function (Blueprint $table) {
            $table->dropForeign(['proceed_account_id']);
            $table->dropColumn('proceed_account_id');
        });
    }
};
