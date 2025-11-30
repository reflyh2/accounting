<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_debt_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('internal_debt_payments', 'status')) {
                $table->string('status')->default('pending')->after('primary_currency_amount'); // pending, approved, rejected, cancelled
            }
        });
    }

    public function down(): void
    {
        Schema::table('internal_debt_payments', function (Blueprint $table) {
            if (Schema::hasColumn('internal_debt_payments', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};


