<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Update master table: add type, partner_id, account_id; remove external_debt_id
        Schema::table('external_debt_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('external_debt_payments', 'type')) {
                $table->string('type')->after('id'); // payable | receivable
            }
            if (!Schema::hasColumn('external_debt_payments', 'partner_id')) {
                $table->foreignId('partner_id')->after('branch_id')->constrained('partners')->onDelete('restrict');
            }
            if (!Schema::hasColumn('external_debt_payments', 'account_id')) {
                $table->foreignId('account_id')->after('partner_id')->constrained('accounts')->onDelete('restrict');
            }
            if (Schema::hasColumn('external_debt_payments', 'external_debt_id')) {
                $table->dropConstrainedForeignId('external_debt_id');
            }
        });

        // Details table creation
        if (!Schema::hasTable('external_debt_payment_details')) {
            Schema::create('external_debt_payment_details', function (Blueprint $table) {
                $table->id();
                $table->foreignId('external_debt_payment_id')->constrained('external_debt_payments')->onDelete('cascade');
                $table->foreignId('external_debt_id')->constrained('external_debts')->onDelete('restrict');
                $table->decimal('amount', 15, 2);
                $table->decimal('primary_currency_amount', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('external_debt_payment_details')) {
            Schema::dropIfExists('external_debt_payment_details');
        }

        Schema::table('external_debt_payments', function (Blueprint $table) {
            if (Schema::hasColumn('external_debt_payments', 'account_id')) {
                $table->dropConstrainedForeignId('account_id');
            }
            if (Schema::hasColumn('external_debt_payments', 'partner_id')) {
                $table->dropConstrainedForeignId('partner_id');
            }
            if (!Schema::hasColumn('external_debt_payments', 'external_debt_id')) {
                $table->foreignId('external_debt_id')->nullable()->constrained('external_debts')->onDelete('cascade');
            }
            if (Schema::hasColumn('external_debt_payments', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};


