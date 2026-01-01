<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('plan_number')->unique();
            $table->date('plan_date');
            $table->date('required_date')->nullable();
            $table->string('source_type')->nullable(); // 'inventory', 'sales', 'manufacturing', 'manual'
            $table->string('status')->default('draft'); // draft, confirmed, closed, cancelled
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'branch_id', 'status']);
            $table->index(['plan_date']);
        });

        Schema::create('purchase_plan_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uom_id')->constrained()->cascadeOnDelete();
            $table->integer('line_number')->default(1);
            $table->string('description')->nullable();
            $table->decimal('planned_qty', 15, 3);
            $table->decimal('ordered_qty', 15, 3)->default(0); // Qty that has been converted to PO
            $table->date('required_date')->nullable();
            $table->string('source_type')->nullable(); // 'inventory', 'sales', 'manufacturing', 'manual'
            $table->unsignedBigInteger('source_ref_id')->nullable(); // Reference to source document
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['purchase_plan_id']);
            $table->index(['product_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_plan_lines');
        Schema::dropIfExists('purchase_plans');
    }
};
