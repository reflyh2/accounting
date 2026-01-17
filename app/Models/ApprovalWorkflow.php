<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalWorkflow extends Model
{
    protected $fillable = [
        'name',
        'document_type',
        'company_id',
        'min_amount',
        'max_amount',
        'is_active',
        'description',
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the company this workflow belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the workflow steps.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowStep::class)->orderBy('step_order');
    }

    /**
     * Get the document approvals using this workflow.
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class);
    }

    /**
     * Find the applicable workflow for a document type and amount.
     */
    public static function findApplicable(string $documentType, ?float $amount = null, ?int $companyId = null): ?self
    {
        return static::query()
            ->where('document_type', $documentType)
            ->where('is_active', true)
            ->when($companyId, fn($q) => $q->where(function($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            }))
            ->when($amount !== null, fn($q) => $q->where(function($q) use ($amount) {
                $q->where(function($q) use ($amount) {
                    $q->whereNull('min_amount')->orWhere('min_amount', '<=', $amount);
                })->where(function($q) use ($amount) {
                    $q->whereNull('max_amount')->orWhere('max_amount', '>=', $amount);
                });
            }))
            ->orderByDesc('company_id') // Prefer company-specific workflows
            ->orderByDesc('min_amount')  // Prefer more specific amount ranges
            ->first();
    }

    /**
     * Scope to filter by document type.
     */
    public function scopeForDocumentType($query, string $documentType)
    {
        return $query->where('document_type', $documentType);
    }

    /**
     * Scope to filter by active workflows.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
