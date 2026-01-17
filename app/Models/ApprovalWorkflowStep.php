<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalWorkflowStep extends Model
{
    protected $fillable = [
        'approval_workflow_id',
        'step_order',
        'required_role_id',
        'min_approvers',
        'allow_parallel',
    ];

    protected $casts = [
        'step_order' => 'integer',
        'min_approvers' => 'integer',
        'allow_parallel' => 'boolean',
    ];

    /**
     * Get the workflow this step belongs to.
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    /**
     * Get the required role for this step.
     */
    public function requiredRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'required_role_id');
    }

    /**
     * Get the document approvals for this step.
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class);
    }

    /**
     * Check if this step has been completed for a document.
     */
    public function isCompleted(string $documentType, int $documentId): bool
    {
        $approvedCount = $this->approvals()
            ->where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->where('status', 'approved')
            ->count();

        return $approvedCount >= $this->min_approvers;
    }

    /**
     * Check if this step is pending for a document.
     */
    public function isPending(string $documentType, int $documentId): bool
    {
        return $this->approvals()
            ->where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->where('status', 'pending')
            ->exists();
    }
}
