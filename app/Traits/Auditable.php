<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for models that require audit logging.
 * Add this trait to models where you want automatic audit trail.
 */
trait Auditable
{
    /**
     * Boot the auditable trait.
     */
    public static function bootAuditable(): void
    {
        // Log creation
        static::created(function (Model $model) {
            if (!$model->shouldAudit('created')) {
                return;
            }

            AuditLog::log(
                AuditLog::ACTION_CREATED,
                $model,
                null,
                $model->getAuditableAttributes(),
            );
        });

        // Log updates
        static::updated(function (Model $model) {
            if (!$model->shouldAudit('updated')) {
                return;
            }

            $original = $model->getOriginal();
            $changed = $model->getDirty();

            // Filter to only auditable attributes
            $auditableFields = $model->getAuditableFields();
            if (!empty($auditableFields)) {
                $changed = array_intersect_key($changed, array_flip($auditableFields));
            }

            if (empty($changed)) {
                return;
            }

            $beforeState = array_intersect_key($original, $changed);

            AuditLog::log(
                AuditLog::ACTION_UPDATED,
                $model,
                $beforeState,
                $changed,
                array_keys($changed),
            );
        });

        // Log deletions
        static::deleted(function (Model $model) {
            if (!$model->shouldAudit('deleted')) {
                return;
            }

            AuditLog::log(
                AuditLog::ACTION_DELETED,
                $model,
                $model->getAuditableAttributes(),
                null,
            );
        });

        // Log restorations (for soft deletes)
        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                if (!$model->shouldAudit('restored')) {
                    return;
                }

                AuditLog::log(
                    AuditLog::ACTION_RESTORED,
                    $model,
                    null,
                    $model->getAuditableAttributes(),
                );
            });
        }
    }

    /**
     * Log a custom action for this model.
     */
    public function logAudit(string $action, ?array $beforeState = null, ?array $afterState = null, ?string $notes = null): AuditLog
    {
        return AuditLog::log(
            $action,
            $this,
            $beforeState,
            $afterState,
            null,
            $notes,
        );
    }

    /**
     * Log a status change for this model.
     */
    public function logStatusChange(string $fromStatus, string $toStatus, ?string $notes = null): AuditLog
    {
        return AuditLog::log(
            AuditLog::ACTION_STATUS_CHANGED,
            $this,
            ['status' => $fromStatus],
            ['status' => $toStatus],
            ['status'],
            $notes,
        );
    }

    /**
     * Get the audit log entries for this model.
     */
    public function auditLogs()
    {
        return AuditLog::forEntity(get_class($this), $this->getKey())
            ->orderByDesc('created_at');
    }

    /**
     * Get the fields that should be audited.
     * Override this method to specify which fields to audit.
     * Return empty array to audit all fields.
     */
    protected function getAuditableFields(): array
    {
        return property_exists($this, 'auditable') ? $this->auditable : [];
    }

    /**
     * Get the fields that should be excluded from auditing.
     */
    protected function getAuditExcludedFields(): array
    {
        return property_exists($this, 'auditExclude')
            ? $this->auditExclude
            : ['password', 'remember_token', 'updated_at', 'created_at'];
    }

    /**
     * Get attributes for audit logging.
     */
    protected function getAuditableAttributes(): array
    {
        $attributes = $this->getAttributes();
        $excludedFields = $this->getAuditExcludedFields();
        $auditableFields = $this->getAuditableFields();

        // Remove excluded fields
        $attributes = array_diff_key($attributes, array_flip($excludedFields));

        // Filter to only auditable fields if specified
        if (!empty($auditableFields)) {
            $attributes = array_intersect_key($attributes, array_flip($auditableFields));
        }

        return $attributes;
    }

    /**
     * Determine if the action should be audited.
     * Override this to add conditional logic.
     */
    protected function shouldAudit(string $action): bool
    {
        // Skip auditing if user is not authenticated (e.g., during seeding)
        if (!auth()->check()) {
            return false;
        }

        return true;
    }

    /**
     * Disable auditing temporarily.
     */
    public static function withoutAuditing(callable $callback)
    {
        static::$auditingDisabled = true;

        try {
            return $callback();
        } finally {
            static::$auditingDisabled = false;
        }
    }

    /**
     * Check if auditing is currently disabled.
     */
    protected static bool $auditingDisabled = false;
}
