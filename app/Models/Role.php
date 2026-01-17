<?php

namespace App\Models;

use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends SpatieRole
{
   /**
     * A role belongs to some users of the model associated with its guard.
     */
    public function users(): BelongsToMany
    {
        return $this->morphedByMany(
            User::class,
            'model',
            config('permission.table_names.model_has_roles'),
            app(PermissionRegistrar::class)->pivotRole,
            config('permission.column_names.model_morph_key')
        );
    }

    /**
     * Get the field permissions for this role.
     */
    public function fieldPermissions(): BelongsToMany
    {
        return $this->belongsToMany(FieldPermission::class, 'role_field_permissions')
            ->withPivot(['can_view', 'can_edit'])
            ->withTimestamps();
    }
    
}
