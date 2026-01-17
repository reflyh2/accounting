<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FieldPermission extends Model
{
    protected $fillable = [
        'model_type',
        'field_name',
        'description',
    ];

    /**
     * Get the roles that have access to this field permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_field_permissions')
            ->withPivot(['can_view', 'can_edit'])
            ->withTimestamps();
    }

    /**
     * Scope to filter by model type.
     */
    public function scopeForModel($query, string $modelType)
    {
        return $query->where('model_type', $modelType);
    }

    /**
     * Scope to filter by field name.
     */
    public function scopeForField($query, string $fieldName)
    {
        return $query->where('field_name', $fieldName);
    }
}
