<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('partner_contacts', 'created_by')) {
                $table->string('created_by')->nullable()->after('notes');
                $table->foreign('created_by')->references('global_id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('partner_contacts', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
