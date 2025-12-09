<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class WorkOrderVariance extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'standard_cost' => 'decimal:6',
        'actual_cost' => 'decimal:6',
        'variance_amount' => 'decimal:6',
        'posted_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('userWorkOrderVariances', function ($builder) {
            if (Auth::check()) {
                $user = User::find(Auth::user()->global_id);

                if ($user->roles->whereIn('access_level', ['company', 'branch_group', 'branch'])->isNotEmpty()) {
                    $builder->whereHas('branch');
                } else {
                    $builder->whereHas('workOrder', function ($query) use ($user) {
                        $query->where('work_orders.user_global_id', $user->global_id);
                    });
                }
            }
        });
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

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by', 'global_id');
    }

    public static function varianceTypes()
    {
        return [
            'usage' => 'Usage Variance',
            'rate' => 'Rate Variance',
            'mix' => 'Mix Variance',
            'total' => 'Total Variance',
        ];
    }

    public function isFavorable(): bool
    {
        return $this->variance_amount < 0;
    }

    public function isUnfavorable(): bool
    {
        return $this->variance_amount > 0;
    }
}
