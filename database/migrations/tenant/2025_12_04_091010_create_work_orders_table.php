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
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('wo_number')->unique();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->string('user_global_id');
            $table->foreignId('bom_id')->constrained('bill_of_materials')->onDelete('cascade');
            $table->foreignId('wip_location_id')->nullable()->constrained('locations')->onDelete('set null');
            $table->decimal('quantity_planned', 15, 6);
            $table->decimal('quantity_produced', 15, 6)->default(0);
            $table->decimal('quantity_scrap', 15, 6)->default(0);
            $table->enum('status', ['draft', 'released', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->date('scheduled_start_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('scheduled_end_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['branch_id', 'status']);
            $table->index('bom_id');
            $table->index('status');

            $table->foreign('user_global_id')->references('global_id')->on('users')->onUpdate('cascade')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
