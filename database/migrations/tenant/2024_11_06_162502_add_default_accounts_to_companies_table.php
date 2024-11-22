<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedBigInteger('default_receivable_account_id')->nullable();
            $table->unsignedBigInteger('default_payable_account_id')->nullable();
            $table->unsignedBigInteger('default_revenue_account_id')->nullable();
            $table->unsignedBigInteger('default_cogs_account_id')->nullable();
            $table->unsignedBigInteger('default_retained_earnings_account_id')->nullable();
            
            $table->foreign('default_receivable_account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('default_payable_account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('default_revenue_account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('default_cogs_account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('default_retained_earnings_account_id')->references('id')->on('accounts')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['default_receivable_account_id']);
            $table->dropForeign(['default_payable_account_id']);
            $table->dropForeign(['default_revenue_account_id']);
            $table->dropForeign(['default_cogs_account_id']);
            $table->dropForeign(['default_retained_earnings_account_id']);
            
            $table->dropColumn([
                'default_receivable_account_id',
                'default_payable_account_id',
                'default_revenue_account_id',
                'default_cogs_account_id',
                'default_retained_earnings_account_id'
            ]);
        });
    }
};