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
        Schema::table('asset_rental_payments', function (Blueprint $table) {
            $table->foreignId('credited_account_id')->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_rental_payments', function (Blueprint $table) {
            $table->dropForeign(['credited_account_id']);
            $table->dropColumn('credited_account_id');
        });
    }
};
