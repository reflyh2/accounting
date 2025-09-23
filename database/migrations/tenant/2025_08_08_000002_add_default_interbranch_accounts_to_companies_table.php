<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('default_interbranch_receivable_account_id')->nullable()->constrained('accounts')->onDelete('restrict');
            $table->foreignId('default_interbranch_payable_account_id')->nullable()->constrained('accounts')->onDelete('restrict');
            $table->foreignId('default_intercompany_receivable_account_id')->nullable()->constrained('accounts')->onDelete('restrict');
            $table->foreignId('default_intercompany_payable_account_id')->nullable()->constrained('accounts')->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['default_interbranch_receivable_account_id']);
            $table->dropForeign(['default_interbranch_payable_account_id']);
            $table->dropForeign(['default_intercompany_receivable_account_id']);
            $table->dropForeign(['default_intercompany_payable_account_id']);
            $table->dropColumn([
                'default_interbranch_receivable_account_id',
                'default_interbranch_payable_account_id',
                'default_intercompany_receivable_account_id',
                'default_intercompany_payable_account_id',
            ]);
        });
    }
};
