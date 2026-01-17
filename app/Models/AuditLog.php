<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    const UPDATED_AT = null; // Audit logs are immutable, no updated_at needed

    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'before_state',
        'after_state',
        'changed_fields',
        'ip_address',
        'user_agent',
        'notes',
    ];

    protected $casts = [
        'before_state' => 'array',
        'after_state' => 'array',
        'changed_fields' => 'array',
        'created_at' => 'datetime',
    ];

    // Common action types
    const ACTION_CREATED = 'created';
    const ACTION_UPDATED = 'updated';
    const ACTION_DELETED = 'deleted';
    const ACTION_APPROVED = 'approved';
    const ACTION_REJECTED = 'rejected';
    const ACTION_POSTED = 'posted';
    const ACTION_CANCELED = 'canceled';
    const ACTION_RESTORED = 'restored';
    const ACTION_STATUS_CHANGED = 'status_changed';

    /**
     * Get the user who performed this action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'global_id');
    }

    /**
     * Get the entity that was audited.
     */
    public function entity(): MorphTo
    {
        return $this->morphTo('entity', 'entity_type', 'entity_id');
    }

    /**
     * Create an audit log entry.
     */
    public static function log(
        string $action,
        Model $entity,
        ?array $beforeState = null,
        ?array $afterState = null,
        ?array $changedFields = null,
        ?string $notes = null,
        ?string $userId = null
    ): self {
        $user = $userId ?? auth()->user()?->global_id;

        return static::create([
            'user_id' => $user,
            'action' => $action,
            'entity_type' => get_class($entity),
            'entity_id' => $entity->getKey(),
            'before_state' => $beforeState,
            'after_state' => $afterState,
            'changed_fields' => $changedFields,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'notes' => $notes,
        ]);
    }

    /**
     * Scope to filter by entity.
     */
    public function scopeForEntity($query, string $entityType, int $entityId)
    {
        return $query->where('entity_type', $entityType)
            ->where('entity_id', $entityId);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeByUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by action.
     */
    public function scopeWithAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get human-readable action label.
     */
    public function getActionLabelAttribute(): string
    {
        return match($this->action) {
            self::ACTION_CREATED => 'Dibuat',
            self::ACTION_UPDATED => 'Diubah',
            self::ACTION_DELETED => 'Dihapus',
            self::ACTION_APPROVED => 'Disetujui',
            self::ACTION_REJECTED => 'Ditolak',
            self::ACTION_POSTED => 'Diposting',
            self::ACTION_CANCELED => 'Dibatalkan',
            self::ACTION_RESTORED => 'Dipulihkan',
            self::ACTION_STATUS_CHANGED => 'Status Diubah',
            default => ucfirst($this->action),
        };
    }
}
