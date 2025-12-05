<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')
                ->constrained('sales_orders')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('company_id')
                ->constrained('companies')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreignId('partner_id')
                ->constrained('partners')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreignId('currency_id')
                ->constrained('currencies')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreignId('location_id')
                ->constrained('locations')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreignId('inventory_transaction_id')
                ->nullable()
                ->constrained('inventory_transactions')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->string('delivery_number')->unique();
            $table->string('status', 32)->index();
            $table->date('delivery_date');
            $table->decimal('total_quantity', 18, 3)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('total_cogs', 18, 4)->default(0);
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->string('posted_by')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('posted_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');

            $table->index(['company_id', 'branch_id', 'delivery_date'], 'idx_sales_deliveries_company_branch_date');
            $table->index(['sales_order_id', 'delivery_date'], 'idx_sales_deliveries_so_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_deliveries');
    }
};


