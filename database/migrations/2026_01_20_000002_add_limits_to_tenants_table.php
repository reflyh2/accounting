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
        Schema::table('tenants', function (Blueprint $table) {
            $table->integer('max_companies')->nullable()->after('data');
            $table->integer('max_branches')->nullable()->after('max_companies');
            $table->integer('max_users')->nullable()->after('max_branches');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['max_companies', 'max_branches', 'max_users']);
        });
    }
};
