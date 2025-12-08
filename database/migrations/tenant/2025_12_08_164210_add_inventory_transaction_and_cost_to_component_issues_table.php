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
            $table->foreignId('inventory_transaction_id')->nullable()->after('location_from_id')->constrained('inventory_transactions')->onDelete('set null');
            $table->decimal('total_material_cost', 15, 6)->default(0)->after('inventory_transaction_id');
            $table->timestamp('posted_at')->nullable()->after('status');
            $table->string('posted_by')->nullable()->after('posted_at');

            $table->index('inventory_transaction_id');
            $table->index('posted_at');

            $table->foreign('posted_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('component_issues', function (Blueprint $table) {
            $table->dropForeign(['inventory_transaction_id']);
            $table->dropForeign(['posted_by']);
            $table->dropIndex(['inventory_transaction_id']);
            $table->dropIndex(['posted_at']);
            $table->dropColumn(['inventory_transaction_id', 'total_material_cost', 'posted_at', 'posted_by']);
        });
    }
};
