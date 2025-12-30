<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('cost_model', 50)->default('direct_expense_per_sale')->after('kind');
            $table->foreignId('prepaid_account_id')
                ->nullable()
                ->after('inventory_account_id')
                ->constrained('accounts')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });

        // Migrate existing kind values to v2 taxonomy
        $kindMappings = [
            'goods' => 'goods_stock',
            'service' => 'service_professional',
            // 'accommodation' stays as 'accommodation'
            'rental' => 'asset_rental',
            'package' => 'travel_package',
        ];

        foreach ($kindMappings as $oldKind => $newKind) {
            DB::table('products')
                ->where('kind', $oldKind)
                ->update(['kind' => $newKind]);
        }

        // Set default cost_model based on new kind
        $costModelDefaults = [
            'goods_stock' => 'inventory_layer',
            'service_professional' => 'job_costing',
            'accommodation' => 'hybrid',
            'asset_rental' => 'asset_usage_costing',
            'travel_package' => 'hybrid',
        ];

        foreach ($costModelDefaults as $kind => $costModel) {
            DB::table('products')
                ->where('kind', $kind)
                ->update(['cost_model' => $costModel]);
        }
    }

    public function down(): void
    {
        // Revert kind values
        $reverseKindMappings = [
            'goods_stock' => 'goods',
            'service_professional' => 'service',
            'asset_rental' => 'rental',
            'travel_package' => 'package',
        ];

        foreach ($reverseKindMappings as $newKind => $oldKind) {
            DB::table('products')
                ->where('kind', $newKind)
                ->update(['kind' => $oldKind]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['prepaid_account_id']);
            $table->dropColumn(['cost_model', 'prepaid_account_id']);
        });
    }
};
