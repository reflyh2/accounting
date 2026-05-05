<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_allocation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('cost_pool_id')->constrained('cost_pools')->cascadeOnUpdate()->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            // allocation_basis = room_nights, seat_nights, rental_days, qty, revenue
            $table->string('allocation_basis', 30)->default('revenue');
            $table->decimal('denominator', 18, 4)->default(0);
            $table->decimal('pool_amount', 18, 2)->default(0);
            // status = draft, posted, reversed
            $table->string('status', 20)->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->string('posted_by')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();

            $table->unique(
                ['company_id', 'cost_pool_id', 'period_start', 'period_end'],
                'uniq_alloc_run_pool_period'
            );
            $table->index(['company_id', 'status'], 'idx_alloc_run_company_status');

            $table->foreign('created_by')->references('global_id')->on('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('updated_by')->references('global_id')->on('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('posted_by')->references('global_id')->on('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('reversed_by')->references('global_id')->on('users')->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_allocation_runs');
    }
};
