<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;
use Stancl\Tenancy\Contracts\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\ResourceSyncing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Model implements Syncable
{
    use HasFactory, ResourceSyncing, HasRoles;

    protected $table = 'users';
    protected $primaryKey = 'global_id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (! $model->global_id) {
                $model->global_id = Str::uuid()->toString();
            }
        });
    }

    public function getGlobalIdentifierKey()
    {
        return $this->getAttribute($this->getGlobalIdentifierKeyName());
    }

    public function getGlobalIdentifierKeyName(): string
    {
        return 'global_id';
    }

    public function getCentralModelName(): string
    {
        return CentralUser::class;
    }

    public function getSyncedAttributeNames(): array
    {
        return [
            'global_id',
            'name',
            'password',
            'email',
        ];
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_has_users', 'user_id', 'branch_id')->withoutGlobalScope('userBranches');
    }

    public function journals(): HasMany
    {
        return $this->hasMany(Journal::class, 'user_global_id', 'global_id');
    }

    public function discountLimits(): HasMany
    {
        return $this->hasMany(UserDiscountLimit::class, 'user_global_id', 'global_id');
    }

    /**
     * Get the audit logs created by this user.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id', 'global_id');
    }

    /**
     * Get the document approvals assigned to this user.
     */
    public function documentApprovals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class, 'approver_id', 'global_id');
    }

    /**
     * Get pending document approvals for this user.
     */
    public function pendingApprovals(): HasMany
    {
        return $this->documentApprovals()->where('status', 'pending');
    }

    /**
     * Check if user can approve a specific document.
     */
    public function canApprove($document, string $documentType): bool
    {
        return app(\App\Services\Security\ApprovalWorkflowService::class)
            ->canUserApprove($document, $documentType, $this);
    }

    /**
     * Check if user has permission to view a specific field on a model.
     */
    public function hasFieldPermission(string $modelType, string $fieldName, string $action = 'can_view'): bool
    {
        // Super administrators always have access
        if ($this->hasRole('Super Administrator')) {
            return true;
        }

        $fieldPermission = FieldPermission::where('model_type', $modelType)
            ->where('field_name', $fieldName)
            ->first();

        // If no field permission is defined, allow access
        if (!$fieldPermission) {
            return true;
        }

        // Check if any of the user's roles have the required permission
        foreach ($this->roles as $role) {
            $pivot = $role->fieldPermissions()
                ->where('field_permission_id', $fieldPermission->id)
                ->first();

            if ($pivot && $pivot->pivot->{$action}) {
                return true;
            }
        }

        return false;
    }
}
