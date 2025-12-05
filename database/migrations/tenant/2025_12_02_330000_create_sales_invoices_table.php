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
        Schema::create('sales_invoices', function (Blueprint $table) {
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
            $table->string('invoice_number', 50)->unique();
            $table->string('status', 30)->default('draft');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('customer_invoice_number', 120)->nullable();
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('delivery_value_base', 18, 4)->default(0);
            $table->decimal('revenue_variance', 18, 2)->default(0);
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

            $table->index(['company_id', 'branch_id'], 'idx_sales_invoice_company_branch');
            $table->index(['partner_id', 'status'], 'idx_sales_invoice_partner_status');
            $table->index('invoice_date', 'idx_sales_invoice_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_invoices');
    }
};
