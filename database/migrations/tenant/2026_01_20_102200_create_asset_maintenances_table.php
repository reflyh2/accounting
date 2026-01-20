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
        Schema::create('asset_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->date('maintenance_date');
            $table->enum('maintenance_type', ['repair', 'service', 'inspection', 'upgrade', 'other'])->default('repair');
            $table->text('description');
            $table->foreignId('vendor_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->decimal('labor_cost', 15, 4)->default(0);
            $table->decimal('parts_cost', 15, 4)->default(0);
            $table->decimal('external_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 4)->default(0);
            $table->enum('status', ['draft', 'completed', 'cancelled'])->default('draft');
            $table->foreignId('cost_entry_id')->nullable()->constrained('cost_entries')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'maintenance_date']);
            $table->index(['asset_id', 'maintenance_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_maintenances');
    }
};
