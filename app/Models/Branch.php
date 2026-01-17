<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'address', 'branch_group_id'];

    protected static function booted()
    {
        static::addGlobalScope('userBranches', function ($builder) {
            if (Auth::check()) {
                $user = User::find(Auth::user()->global_id);
                
                // Skip scope if user doesn't exist in tenant DB yet (e.g., during seeding)
                if (!$user) {
                    return;
                }
                
                $branchIds = DB::table('branch_has_users')->select('branch_id')->where('user_id', $user->global_id)->get()->pluck('branch_id');
                $branchGroupIds = DB::table('branches')->select('branch_group_id')->whereIn('branches.id', $branchIds)->get()->pluck('branch_group_id');
                $companyIds = BranchGroup::withoutGlobalScopes()->whereIn('branch_groups.id', $branchGroupIds)->pluck('company_id');

                if ($user->roles->contains('access_level', 'company')) 
                {
                    $builder->whereIn('branch_group_id', function ($query) use ($companyIds) {
                        $query->select('id')
                              ->from('branch_groups')
                              ->whereIn('company_id', $companyIds);
                    });
                }
                else if ($user->roles->contains('access_level', 'branch_group'))
                {
                    $builder->whereIn('branch_group_id', $branchGroupIds);
                }
                else if ($user->roles->contains('access_level', 'branch'))
                {
                    $builder->whereIn('branches.id', $branchIds);
                }
                else 
                {
                    $builder->whereHas('users', function ($query) use ($user) {
                        $query->where('users.global_id', $user->global_id);
                    });
                }
            }
        });
    }

    public function branchGroupAll()
    {
        return $this->belongsTo(BranchGroup::class)->withoutGlobalScope('userBranchGroups');
    }


    public function branchGroup()
    {
        return $this->belongsTo(BranchGroup::class);
    }
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'branch_has_users', 'branch_id', 'user_id');
    }

    public function journals()
    {
        return $this->hasMany(Journal::class);
    }
}
