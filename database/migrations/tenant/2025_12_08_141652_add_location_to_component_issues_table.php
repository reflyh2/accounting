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
        Schema::table('component_issues', function (Blueprint $table) {
            $table->foreignId('location_from_id')->nullable()->after('branch_id')->constrained('locations')->onDelete('set null');
            $table->index('location_from_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('component_issues', function (Blueprint $table) {
            $table->dropForeign(['location_from_id']);
            $table->dropIndex(['location_from_id']);
            $table->dropColumn('location_from_id');
        });
    }
};
