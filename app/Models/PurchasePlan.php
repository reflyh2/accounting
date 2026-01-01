<?php

namespace App\Models;

use App\Domain\Documents\StateMachine\Definitions\PurchasePlanStates;
use App\Domain\Documents\StateMachine\DocumentStateMachineDefinition;
use App\Enums\Documents\PurchasePlanStatus;
use App\Traits\DocumentStateMachine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchasePlan extends Model
{
    use HasFactory;
    use SoftDeletes;
    use DocumentStateMachine;

    protected $guarded = [];

    protected $casts = [
        'plan_date' => 'date',
        'required_date' => 'date',
        'confirmed_at' => 'datetime',
        'closed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (PurchasePlan $model): void {
            $model->status ??= PurchasePlanStatus::DRAFT->value;
        });
    }

    protected static function stateMachineDefinition(): DocumentStateMachineDefinition
    {
        return PurchasePlanStates::definition();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function lines()
    {
        return $this->hasMany(PurchasePlanLine::class)->orderBy('line_number');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    public function confirmer()
    {
        return $this->belongsTo(User::class, 'confirmed_by', 'global_id');
    }

    public function statusEnum(): PurchasePlanStatus
    {
        return PurchasePlanStatus::from($this->status);
    }
}
