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
        // Approval workflows table - defines workflow configurations per document type
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('document_type');              // e.g., 'purchase_order', 'sales_invoice'
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('min_amount', 20, 2)->nullable();
            $table->decimal('max_amount', 20, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['document_type', 'is_active']);
        });

        // Approval workflow steps table - defines the steps in a workflow
        Schema::create('approval_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_workflow_id')->constrained()->cascadeOnDelete();
            $table->integer('step_order');
            $table->foreignId('required_role_id')->constrained('roles')->cascadeOnDelete();
            $table->integer('min_approvers')->default(1);
            $table->boolean('allow_parallel')->default(false);
            $table->timestamps();

            $table->unique(['approval_workflow_id', 'step_order']);
        });

        // Document approvals table - tracks actual approval actions on documents
        Schema::create('document_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('document_type');
            $table->unsignedBigInteger('document_id');
            $table->foreignId('approval_workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_workflow_step_id')->constrained()->cascadeOnDelete();
            $table->string('approver_id');               // user global_id
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('notes')->nullable();
            $table->timestamp('actioned_at')->nullable();
            $table->timestamps();

            $table->foreign('approver_id')->references('global_id')->on('users');
            $table->index(['document_type', 'document_id']);
            $table->index(['approver_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_approvals');
        Schema::dropIfExists('approval_workflow_steps');
        Schema::dropIfExists('approval_workflows');
    }
};
