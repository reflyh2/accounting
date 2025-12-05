<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')
                ->constrained('sales_orders')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreignId('sales_delivery_id')
                ->constrained('sales_deliveries')
                ->onUpdate('cascade')
                ->onDelete('restrict');
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
            $table->foreignId('location_id')
                ->constrained('locations')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreignId('currency_id')
                ->constrained('currencies')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->foreignId('inventory_transaction_id')
                ->nullable()
                ->constrained('inventory_transactions')
                ->onUpdate('cascade')
                ->onDelete('restrict');
            $table->string('return_number', 50)->unique();
            $table->string('status', 30)->default('draft');
            $table->date('return_date');
            $table->string('reason_code', 50)->nullable();
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->decimal('total_quantity', 18, 3)->default(0);
            $table->decimal('total_value', 18, 4)->default(0);
            $table->decimal('total_value_base', 18, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->string('posted_by')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('posted_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('created_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('updated_by')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');

            $table->index(['company_id', 'branch_id'], 'idx_sales_returns_company_branch');
            $table->index(['sales_order_id', 'return_date'], 'idx_sales_returns_so_date');
            $table->index('status', 'idx_sales_returns_status');
            $table->index('reason_code', 'idx_sales_returns_reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};
