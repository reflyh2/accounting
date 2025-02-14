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
        Schema::table('asset_financing_payments', function (Blueprint $table) {
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_financing_payments', function (Blueprint $table) {
            $table->dropForeign(['journal_id']);
            $table->dropColumn('journal_id');
        });
    }
}; 