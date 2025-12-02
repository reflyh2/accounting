<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transaction_lines', function (Blueprint $table) {
            $table->string('effect', 10)->default('in')->after('quantity');
        });

        DB::statement("
            UPDATE inventory_transaction_lines AS lines
            SET effect = 'out'
            FROM inventory_transactions AS tx
            WHERE lines.inventory_transaction_id = tx.id
              AND tx.transaction_type IN ('issue','transfer')
        ");

        Schema::create('inventory_cost_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_transaction_line_id')
                ->constrained('inventory_transaction_lines')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('cost_layer_id')
                ->constrained('cost_layers')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_cost', 18, 4);
            $table->timestamps();

            $table->index('cost_layer_id', 'idx_cost_consumption_layer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_consumptions');

        Schema::table('inventory_transaction_lines', function (Blueprint $table) {
            $table->dropColumn('effect');
        });
    }
};

