<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('costing_policy', 32)
                ->default('fifo')
                ->after('default_intercompany_payable_account_id');

            $table->string('reservation_strictness', 32)
                ->default('soft')
                ->after('costing_policy');

            $table->boolean('default_backflush')
                ->default(false)
                ->after('reservation_strictness');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'costing_policy',
                'reservation_strictness',
                'default_backflush',
            ]);
        });
    }
};

