<?php

namespace App\Traits;

use App\Models\Branch;
use App\Models\BranchGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Trait for applying access level restrictions to document models.
 * 
 * Document models using this trait must have a `branch_id` column or
 * define a custom `getAccessLevelColumn()` method.
 * 
 * Access levels in order of precedence:
 * - company: Can access all documents from all branches in their companies
 * - branch_group: Can access documents from all branches in their branch groups
 * - branch: Can access documents only from their assigned branches
 * - own: Can only access documents they created (uses created_by column)
 */
trait HasAccessLevelScope
{
    /**
     * Boot the trait.
     */
    public static function bootHasAccessLevelScope(): void
    {
        static::addGlobalScope('accessLevel', function (Builder $builder) {
            static::applyAccessLevelScope($builder);
        });
    }

    /**
     * Apply access level scoping to the query builder.
     */
    protected static function applyAccessLevelScope(Builder $builder): void
    {
        if (!Auth::check()) {
            return;
        }

        $user = User::find(Auth::user()->global_id);

        // Skip scope if user doesn't exist in tenant DB yet (e.g., during seeding)
        if (!$user) {
            return;
        }

        // Get user's branch context
        $branchIds = DB::table('branch_has_users')
            ->select('branch_id')
            ->where('user_id', $user->global_id)
            ->pluck('branch_id');

        $branchGroupIds = DB::table('branches')
            ->select('branch_group_id')
            ->whereIn('id', $branchIds)
            ->pluck('branch_group_id');

        $companyIds = BranchGroup::withoutGlobalScopes()
            ->whereIn('id', $branchGroupIds)
            ->pluck('company_id');

        $accessColumn = static::getAccessLevelColumn();
        $tableName = (new static)->getTable();
        $createdByColumn = static::getCreatedByColumn();

        // Apply scope based on user's highest access level
        if ($user->roles->contains('access_level', 'company')) {
            // Company level: Access all documents from companies user is assigned to
            if ($accessColumn === 'branch_id') {
                $builder->whereIn("{$tableName}.branch_id", function ($query) use ($companyIds) {
                    $query->select('branches.id')
                        ->from('branches')
                        ->join('branch_groups', 'branches.branch_group_id', '=', 'branch_groups.id')
                        ->whereIn('branch_groups.company_id', $companyIds);
                });
            } elseif ($accessColumn === 'company_id') {
                $builder->whereIn("{$tableName}.company_id", $companyIds);
            }
        } elseif ($user->roles->contains('access_level', 'branch_group')) {
            // Branch group level: Access documents from all branches in user's branch groups
            if ($accessColumn === 'branch_id') {
                $builder->whereIn("{$tableName}.branch_id", function ($query) use ($branchGroupIds) {
                    $query->select('id')
                        ->from('branches')
                        ->whereIn('branch_group_id', $branchGroupIds);
                });
            } elseif ($accessColumn === 'company_id') {
                $builder->whereIn("{$tableName}.company_id", function ($query) use ($branchGroupIds) {
                    $query->select('company_id')
                        ->from('branch_groups')
                        ->whereIn('id', $branchGroupIds);
                });
            }
        } elseif ($user->roles->contains('access_level', 'branch')) {
            // Branch level: Access documents only from user's assigned branches
            if ($accessColumn === 'branch_id') {
                $builder->whereIn("{$tableName}.branch_id", $branchIds);
            } elseif ($accessColumn === 'company_id') {
                // For company_id based models at branch level, still use branch context
                $builder->whereIn("{$tableName}.company_id", $companyIds);
            }
        } else {
            // Own level: Access only documents created by this user
            if ($createdByColumn) {
                $builder->where("{$tableName}.{$createdByColumn}", $user->global_id);
            } else {
                // Fallback: if no created_by column, use branch restriction
                if ($accessColumn === 'branch_id') {
                    $builder->whereIn("{$tableName}.branch_id", $branchIds);
                }
            }
        }
    }

    /**
     * Get the column name used for access level restriction.
     * Override this method if your model uses a different column.
     */
    public static function getAccessLevelColumn(): string
    {
        return 'branch_id';
    }

    /**
     * Get the column name for the document creator.
     * Used for 'own' access level.
     */
    public static function getCreatedByColumn(): ?string
    {
        return 'created_by';
    }

    /**
     * Query scope to bypass access level restrictions.
     */
    public function scopeWithoutAccessLevel(Builder $query): Builder
    {
        return $query->withoutGlobalScope('accessLevel');
    }
}
