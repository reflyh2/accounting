<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onUpdate('cascade')->onDelete('restrict');
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            // type = warehouse, store, room, yard, vehicle
            $table->string('type', 50);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });

        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->onUpdate('cascade')->onDelete('cascade');
            $table->string('lot_code', 80);
            $table->date('mfg_date')->nullable();
            $table->date('expiry_date')->nullable();
            // status = active, quarantine, expired
            $table->string('status', 50)->default('active');
            
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->unique(['product_variant_id', 'lot_code']);

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });

        Schema::create('serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->onUpdate('cascade')->onDelete('cascade');
            $table->string('serial_no', 120)->unique();
            // status = in_stock, rented, sold, lost, retired
            $table->string('status', 50)->default('in_stock');

            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('location_id')->constrained('locations')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('lot_id')->nullable()->constrained('lots')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('serial_id')->nullable()->constrained('serials')->onUpdate('cascade')->onDelete('restrict');
            $table->decimal('qty_on_hand', 18, 3)->default(0);
            $table->decimal('qty_reserved', 18, 3)->default(0);
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->index(['product_variant_id', 'location_id'], 'idx_inv_item_v_l');
            $table->index('lot_id', 'idx_inv_item_lot');
            $table->index('serial_id', 'idx_inv_item_serial');

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });

        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 50)->unique();
            $table->date('transaction_date');
            // transaction_type = receipt, issue, adjustment, transfer
            $table->string('transaction_type', 50);
            $table->text('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('location_id_from')->nullable()->constrained('locations')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('location_id_to')->nullable()->constrained('locations')->onUpdate('cascade')->onDelete('restrict');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });

        Schema::create('inventory_transaction_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_transaction_id')->constrained('inventory_transactions')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('product_variant_id')->constrained('product_variants')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('uom_id')->constrained('uoms')->onUpdate('cascade')->onDelete('restrict');
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->foreignId('lot_id')->nullable()->constrained('lots')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('serial_id')->nullable()->constrained('serials')->onUpdate('cascade')->onDelete('restrict');
            $table->unsignedBigInteger('ref_invoice_detail_id')->nullable();
            $table->unsignedBigInteger('ref_booking_line_id')->nullable();
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->index('inventory_transaction_id', 'idx_txn_line_txn');
            $table->index('product_variant_id', 'idx_txn_line_variant');

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });

        Schema::create('cost_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('location_id')->constrained('locations')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('lot_id')->nullable()->constrained('lots')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('serial_id')->nullable()->constrained('serials')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('inventory_transaction_line_id')->constrained('inventory_transaction_lines')->onUpdate('cascade')->onDelete('restrict');
            $table->decimal('qty_remaining', 18, 3);
            $table->decimal('unit_cost', 18, 4);
            // valuation_method = fifo, moving_avg
            $table->string('valuation_method', 50)->default('fifo');
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->index(['product_variant_id', 'location_id'], 'idx_cost_layer_v_l');

            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_layers');
        Schema::dropIfExists('inventory_transaction_lines');
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('serials');
        Schema::dropIfExists('lots');
        Schema::dropIfExists('locations');
    }
};

