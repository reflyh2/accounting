<?php

namespace App\Traits;

use App\Models\BranchGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Trait for applying access level restrictions to PartnerContact model.
 * 
 * Access levels:
 * - company: Can access contacts of partners belonging to user's companies
 * - branch_group: Can access contacts of partners belonging to companies in user's branch groups
 * - branch: Can access contacts of partners belonging to companies in user's branches
 * - own: Can only access contacts they created (uses created_by column)
 */
trait HasPartnerContactAccessScope
{
    /**
     * Boot the trait.
     */
    public static function bootHasPartnerContactAccessScope(): void
    {
        static::addGlobalScope('partnerContactAccess', function (Builder $builder) {
            static::applyPartnerContactAccessScope($builder);
        });
    }

    /**
     * Apply access level scoping to the query builder.
     */
    protected static function applyPartnerContactAccessScope(Builder $builder): void
    {
        if (!Auth::check()) {
            return;
        }

        $user = User::find(Auth::user()->global_id);

        // Skip scope if user doesn't exist in tenant DB yet
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

        $tableName = (new static)->getTable();

        // Apply scope based on user's highest access level
        if ($user->roles->contains('access_level', 'company')) {
            // Company level: Access contacts of partners in user's companies
            $builder->whereIn("{$tableName}.partner_id", function ($query) use ($companyIds) {
                $query->select('partners.id')
                    ->from('partners')
                    ->join('partner_company', 'partners.id', '=', 'partner_company.partner_id')
                    ->whereIn('partner_company.company_id', $companyIds);
            });
        } elseif ($user->roles->contains('access_level', 'branch_group')) {
            // Branch group level: Access contacts of partners in branch group companies
            $builder->whereIn("{$tableName}.partner_id", function ($query) use ($branchGroupIds) {
                $query->select('partners.id')
                    ->from('partners')
                    ->join('partner_company', 'partners.id', '=', 'partner_company.partner_id')
                    ->whereIn('partner_company.company_id', function ($subQuery) use ($branchGroupIds) {
                        $subQuery->select('company_id')
                            ->from('branch_groups')
                            ->whereIn('id', $branchGroupIds);
                    });
            });
        } elseif ($user->roles->contains('access_level', 'branch')) {
            // Branch level: Access contacts of partners in companies from user's branches
            $builder->whereIn("{$tableName}.partner_id", function ($query) use ($companyIds) {
                $query->select('partners.id')
                    ->from('partners')
                    ->join('partner_company', 'partners.id', '=', 'partner_company.partner_id')
                    ->whereIn('partner_company.company_id', $companyIds);
            });
        } else {
            // Own level: Access only contacts created by this user
            $builder->where("{$tableName}.created_by", $user->global_id);
        }
    }

    /**
     * Query scope to bypass access level restrictions.
     */
    public function scopeWithoutPartnerContactAccess(Builder $query): Builder
    {
        return $query->withoutGlobalScope('partnerContactAccess');
    }
}
