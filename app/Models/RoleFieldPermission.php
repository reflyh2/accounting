<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleFieldPermission extends Model
{
    protected $fillable = [
        'role_id',
        'field_permission_id',
        'can_view',
        'can_edit',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_edit' => 'boolean',
    ];

    /**
     * Get the role.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the field permission.
     */
    public function fieldPermission(): BelongsTo
    {
        return $this->belongsTo(FieldPermission::class);
    }
}
