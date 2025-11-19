<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\DebtStatus;

class ExternalDebt extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'float',
        'primary_currency_amount' => 'float',
        'status' => DebtStatus::class,
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            $prefix = match("$model->type") {
                'payable' => 'XP',
                'receivable' => 'XR',
                default => 'ED',
            };
    
            $year = date('y', strtotime($model->issue_date));
            $paddedBranchId = str_pad($model->branch_id, 3, '0', STR_PAD_LEFT);
    
            $last = self::where('branch_id', $model->branch_id)
                ->whereYear('issue_date', date('Y', strtotime($model->issue_date)))
                ->where('type', $model->type)
                ->withTrashed()
                ->orderBy('number', 'desc')
                ->first();
    
            $lastNumber = $last ? intval(substr($last->number, -5)) : 0;
            $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
    
            $model->number = $prefix . '.' . $year . '.' . $paddedBranchId . '.' . $newNumber;
        });
    }

    public static function statusStyles(): array
    {
        return DebtStatus::styles();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'global_id');
    }

    public function debtAccount()
    {
        return $this->belongsTo(Account::class, 'debt_account_id');
    }

    public function offsetAccount()
    {
        return $this->belongsTo(Account::class, 'offset_account_id');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
}


