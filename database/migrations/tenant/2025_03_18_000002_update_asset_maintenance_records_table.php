<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('asset_maintenance_records', function (Blueprint $table) {
            // Drop existing maintenance_type column
            $table->dropColumn('maintenance_type');
            
            // Add new columns
            $table->foreignId('maintenance_type_id')->after('asset_id')->constrained('asset_maintenance_types');
            $table->foreignId('credited_account_id')->nullable()->after('cost')->constrained('accounts');
            $table->foreignId('journal_id')->nullable()->after('credited_account_id')->constrained('journals');
            $table->string('payment_status')->default('pending')->after('journal_id')->comment('Options: pending, paid');
            $table->date('payment_date')->nullable()->after('payment_status');
        });
    }

    public function down()
    {
        Schema::table('asset_maintenance_records', function (Blueprint $table) {
            // Drop foreign keys first to avoid constraints
            $table->dropForeign(['maintenance_type_id']);
            $table->dropForeign(['credited_account_id']);
            $table->dropForeign(['journal_id']);
            
            // Remove columns we added
            $table->dropColumn([
                'maintenance_type_id',
                'credited_account_id',
                'journal_id',
                'payment_status',
                'payment_date'
            ]);
            
            // Add back the original column
            $table->string('maintenance_type')->after('maintenance_date');
        });
    }
}; 