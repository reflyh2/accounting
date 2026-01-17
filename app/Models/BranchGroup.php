<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BranchGroup extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'company_id'];

    protected static function booted()
    {
        static::addGlobalScope('userBranchGroups', function ($builder) {
            if (Auth::check()) {
                $user = User::find(Auth::user()->global_id);
                
                // Skip scope if user doesn't exist in tenant DB yet (e.g., during seeding)
                if (!$user) {
                    return;
                }
                
                $userId = $user->global_id;

                // Check if the user has company-level access
                $hasCompanyAccess = $user->roles->contains('access_level', 'company');
                // $hasCompanyAccess = false;

                if ($hasCompanyAccess) {
                    // User can see all branch groups of their companies
                    $builder->whereIn('company_id', function ($query) use ($user) {
                        $query->select('company_id')
                              ->from('branch_groups')
                              ->whereIn('branch_groups.id', function ($subQuery) use ($user) {
                                  $subQuery->select('branch_group_id')
                                           ->from('branches')
                                           ->whereIn('branches.id', $user->branches->pluck('id'));
                              });
                    });
                } else {
                    // User can only see branch groups they belong to
                    $builder->whereHas('branches.users', function ($query) use ($userId) {
                        $query->where('users.global_id', $userId);
                    });
                }
            }
        });
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function branchesAll()
    {
        return $this->hasMany(Branch::class)->withoutGlobalScope('userBranches');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function companyAll()
    {
        return $this->belongsTo(Company::class)->withoutGlobalScope('userCompanies');
    }
}
