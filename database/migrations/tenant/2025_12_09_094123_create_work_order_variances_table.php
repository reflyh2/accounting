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
        Schema::create('work_order_variances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')
                ->constrained('work_orders')
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
            $table->string('variance_type', 50); // usage, rate, mix, total
            $table->decimal('standard_cost', 18, 6)->default(0);
            $table->decimal('actual_cost', 18, 6)->default(0);
            $table->decimal('variance_amount', 18, 6)->default(0); // positive = unfavorable, negative = favorable
            $table->text('description')->nullable();
            $table->foreignId('journal_id')
                ->nullable()
                ->constrained('journals')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->timestamp('posted_at')->nullable();
            $table->string('posted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('posted_by')
                ->references('global_id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->index(['work_order_id', 'variance_type'], 'idx_wov_wo_type');
            $table->index(['company_id', 'branch_id'], 'idx_wov_company_branch');
            $table->index('journal_id', 'idx_wov_journal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_variances');
    }
};
