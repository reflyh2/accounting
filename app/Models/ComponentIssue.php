<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComponentIssue extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $issueDate = date('y', strtotime($model->issue_date ?? now()));
            $lastIssue = self::whereYear('issue_date', date('Y', strtotime($model->issue_date ?? now())))
                          ->where('branch_id', $model->branch_id)
                          ->withTrashed()
                          ->orderBy('issue_number', 'desc')
                          ->first();
            $lastNumber = $lastIssue ? intval(substr($lastIssue->issue_number, -5)) : 0;
            $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
            $model->issue_number = 'CI.' . str_pad($model->company_id, 2, '0', STR_PAD_LEFT) . str_pad($model->branch_id, 3, '0', STR_PAD_LEFT) . $issueDate . '.' . $newNumber;
        });

        static::addGlobalScope('userComponentIssues', function ($builder) {
            if (Auth::check()) {
                $user = User::find(Auth::user()->global_id);

                if ($user->roles->whereIn('access_level', ['company', 'branch_group', 'branch'])->isNotEmpty()) {
                    $builder->whereHas('branch');
                } else {
                    $builder->where('component_issues.user_global_id', $user->global_id);
                }
            }
        });
    }

    public function componentIssueLines()
    {
        return $this->hasMany(ComponentIssueLine::class)->orderBy('line_number');
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function locationFrom()
    {
        return $this->belongsTo(Location::class, 'location_from_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_global_id', 'global_id');
    }

    public function inventoryTransaction()
    {
        return $this->belongsTo(InventoryTransaction::class);
    }

    protected $casts = [
        'issue_date' => 'date',
        'posted_at' => 'datetime',
        'total_material_cost' => 'decimal:6',
    ];

    public static function statuses()
    {
        return [
            'draft' => 'Draft',
            'posted' => 'Posted',
        ];
    }
}
