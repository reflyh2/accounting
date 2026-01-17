<?php

namespace App\Traits;

use App\Models\FieldPermission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Trait for models that have field-level access control.
 * Add this trait to models with sensitive fields that need restricted access.
 */
trait HasFieldPermissions
{
    /**
     * Get the model type for field permissions.
     */
    protected function getFieldPermissionModelType(): string
    {
        return static::class;
    }

    /**
     * Check if the current user can view a specific field.
     */
    public function canViewField(string $fieldName): bool
    {
        return $this->checkFieldPermission($fieldName, 'can_view');
    }

    /**
     * Check if the current user can edit a specific field.
     */
    public function canEditField(string $fieldName): bool
    {
        return $this->checkFieldPermission($fieldName, 'can_edit');
    }

    /**
     * Check if the user has a specific permission for a field.
     */
    protected function checkFieldPermission(string $fieldName, string $permissionType): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Super administrators always have access
        if ($user->hasRole('Super Administrator')) {
            return true;
        }

        $modelType = $this->getFieldPermissionModelType();
        
        // Check if field permission exists
        $fieldPermission = FieldPermission::forModel($modelType)
            ->forField($fieldName)
            ->first();

        // If no field permission is defined, allow access (no restriction)
        if (!$fieldPermission) {
            return true;
        }

        // Check if any of the user's roles have the required permission
        foreach ($user->roles as $role) {
            $pivot = $role->fieldPermissions()
                ->where('field_permission_id', $fieldPermission->id)
                ->first();

            if ($pivot && $pivot->pivot->{$permissionType}) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get visible fields for the current user.
     * Returns only fields the user has permission to view.
     */
    public function getVisibleAttributes(): array
    {
        $visibleAttributes = [];

        foreach ($this->getAttributes() as $key => $value) {
            if ($this->canViewField($key)) {
                $visibleAttributes[$key] = $value;
            }
        }

        return $visibleAttributes;
    }

    /**
     * Get editable fields for the current user.
     */
    public function getEditableFields(): array
    {
        $editableFields = [];

        foreach ($this->getFillable() as $field) {
            if ($this->canEditField($field)) {
                $editableFields[] = $field;
            }
        }

        return $editableFields;
    }

    /**
     * Define which fields are sensitive and require permission checks.
     * Override this method in your model to specify sensitive fields.
     */
    public static function sensitiveFields(): array
    {
        return [];
    }
}
