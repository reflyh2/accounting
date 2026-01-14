<?php

namespace App\Models;

use App\Enums\Documents\DocumentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class DocumentTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'document_type',
        'name',
        'content',
        'css_styles',
        'is_default',
        'is_active',
        'page_size',
        'page_orientation',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'document_type' => DocumentType::class,
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ─────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ─────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────

    public function scopeForCompany(Builder $query, ?int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForType(Builder $query, DocumentType|string $type): Builder
    {
        $typeValue = $type instanceof DocumentType ? $type->value : $type;
        return $query->where('document_type', $typeValue);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('company_id');
    }

    // ─────────────────────────────────────────────
    // Static Methods
    // ─────────────────────────────────────────────

    /**
     * Resolve the template to use for a given company and document type.
     * Falls back to global default if no company-specific template exists.
     */
    public static function resolveTemplate(?int $companyId, DocumentType|string $type): ?self
    {
        $typeValue = $type instanceof DocumentType ? $type->value : $type;

        // First, try to find company-specific default template
        if ($companyId) {
            $template = static::forCompany($companyId)
                ->forType($typeValue)
                ->active()
                ->default()
                ->first();

            if ($template) {
                return $template;
            }
        }

        // Fallback to global default template
        return static::global()
            ->forType($typeValue)
            ->active()
            ->default()
            ->first();
    }

    /**
     * Get all templates available for a company (including global defaults).
     */
    public static function getTemplatesForCompany(?int $companyId, DocumentType|string $type): \Illuminate\Database\Eloquent\Collection
    {
        $typeValue = $type instanceof DocumentType ? $type->value : $type;

        return static::where(function ($query) use ($companyId) {
            $query->whereNull('company_id')
                ->orWhere('company_id', $companyId);
        })
            ->forType($typeValue)
            ->active()
            ->orderByDesc('company_id') // Company-specific first
            ->orderByDesc('is_default')
            ->get();
    }
}
