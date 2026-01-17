<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->string('sales_person_id')->nullable()->after('notes');
            $table->foreign('sales_person_id')
                ->references('global_id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->index('sales_person_id', 'idx_si_sales_person');
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropForeign(['sales_person_id']);
            $table->dropIndex('idx_si_sales_person');
            $table->dropColumn('sales_person_id');
        });
    }
};
