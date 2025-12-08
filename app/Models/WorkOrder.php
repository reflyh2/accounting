<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $woDate = date('y', strtotime($model->scheduled_start_date ?? now()));
            $lastWo = self::whereYear('scheduled_start_date', date('Y', strtotime($model->scheduled_start_date ?? now())))
                          ->where('branch_id', $model->branch_id)
                          ->withTrashed()
                          ->orderBy('wo_number', 'desc')
                          ->first();
            $lastNumber = $lastWo ? intval(substr($lastWo->wo_number, -5)) : 0;
            $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
            $model->wo_number = 'WO.' . str_pad($model->company_id, 2, '0', STR_PAD_LEFT) . str_pad($model->branch_id, 3, '0', STR_PAD_LEFT) . $woDate . '.' . $newNumber;
        });

        static::addGlobalScope('userWorkOrders', function ($builder) {
            if (Auth::check()) {
                $user = User::find(Auth::user()->global_id);

                if ($user->roles->whereIn('access_level', ['company', 'branch_group', 'branch'])->isNotEmpty()) {
                    $builder->whereHas('branch');
                } else {
                    $builder->where('work_orders.user_global_id', $user->global_id);
                }
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_global_id', 'global_id');
    }

    public function bom()
    {
        return $this->belongsTo(BillOfMaterial::class, 'bom_id');
    }

    public function finishedProductVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'finished_product_variant_id');
    }

    public function wipLocation()
    {
        return $this->belongsTo(Location::class, 'wip_location_id');
    }

    public static function workOrderStatuses()
    {
        return [
            'draft' => 'Draft',
            'released' => 'Released',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
    }

    public static function allowedTransitions()
    {
        return [
            'draft' => ['released', 'cancelled'],
            'released' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => [],
        ];
    }

    public function canTransitionTo($newStatus)
    {
        $currentStatus = $this->status;
        $allowedTransitions = self::allowedTransitions()[$currentStatus] ?? [];

        return in_array($newStatus, $allowedTransitions);
    }

    public function transitionTo($newStatus)
    {
        if (!$this->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException("Cannot transition from {$this->status} to {$newStatus}");
        }

        $this->status = $newStatus;

        // Set actual dates based on status
        if ($newStatus === 'in_progress' && !$this->actual_start_date) {
            $this->actual_start_date = now()->toDateString();
        } elseif ($newStatus === 'completed' && !$this->actual_end_date) {
            $this->actual_end_date = now()->toDateString();
        }

        $this->save();

        return $this;
    }

    public function componentIssues()
    {
        return $this->hasMany(ComponentIssue::class);
    }

    public function componentIssueLines()
    {
        return $this->hasManyThrough(ComponentIssueLine::class, ComponentIssue::class);
    }

    public function getRemainingQuantityAttribute($componentId)
    {
        return $this->quantity_planned - $this->quantity_produced;
    }

    public function getProgressPercentageAttribute()
    {
        if ($this->quantity_planned <= 0) {
            return 0;
        }

        return round(($this->quantity_produced / $this->quantity_planned) * 100, 2);
    }
}
