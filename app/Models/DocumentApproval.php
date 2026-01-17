<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DocumentApproval extends Model
{
    protected $fillable = [
        'document_type',
        'document_id',
        'approval_workflow_id',
        'approval_workflow_step_id',
        'approver_id',
        'status',
        'notes',
        'actioned_at',
    ];

    protected $casts = [
        'actioned_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * Get the workflow.
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    /**
     * Get the workflow step.
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflowStep::class, 'approval_workflow_step_id');
    }

    /**
     * Get the approver.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id', 'global_id');
    }

    /**
     * Get the document being approved.
     */
    public function document(): MorphTo
    {
        return $this->morphTo('document', 'document_type', 'document_id');
    }

    /**
     * Approve this approval request.
     */
    public function approve(?string $notes = null): self
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'notes' => $notes,
            'actioned_at' => now(),
        ]);

        return $this;
    }

    /**
     * Reject this approval request.
     */
    public function reject(?string $notes = null): self
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'notes' => $notes,
            'actioned_at' => now(),
        ]);

        return $this;
    }

    /**
     * Scope to filter by pending status.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to filter by approved status.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope to filter by rejected status.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope to filter by document.
     */
    public function scopeForDocument($query, string $documentType, int $documentId)
    {
        return $query->where('document_type', $documentType)
            ->where('document_id', $documentId);
    }
}
