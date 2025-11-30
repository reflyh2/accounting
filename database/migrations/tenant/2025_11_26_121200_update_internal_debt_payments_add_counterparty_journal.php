<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_debt_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('internal_debt_payments', 'counterparty_journal_id')) {
                $table->foreignId('counterparty_journal_id')->nullable()->after('journal_id')->constrained('journals')->onUpdate('cascade')->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('internal_debt_payments', function (Blueprint $table) {
            if (Schema::hasColumn('internal_debt_payments', 'counterparty_journal_id')) {
                $table->dropConstrainedForeignId('counterparty_journal_id');
            }
        });
    }
};


