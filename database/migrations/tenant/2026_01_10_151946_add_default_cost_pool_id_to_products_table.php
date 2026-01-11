<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('default_cost_pool_id')
                ->nullable()
                ->after('cost_model')
                ->constrained('cost_pools')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['default_cost_pool_id']);
            $table->dropColumn('default_cost_pool_id');
        });
    }
};
