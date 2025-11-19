<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use App\Traits\AvoidDuplicateConstraintOnSoftDelete;

class ExternalDebtPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // type must be provided by controller: 'payable' | 'receivable'

            // Generate payment number:
            // Prefix based on external debt type (payable/receivable)
            // Format: XPP|XRP.YY.BBB.NNNNN
            //  - XPP: External Payable Payment
            //  - XRP: External Receivable Payment
            //  - YY: year (2 digits) from payment_date
            //  - BBB: branch_id padded to 3 digits
            //  - NNNNN: sequence per branch-year-type
            $type = $model->type ?? null;
            $prefix = match ("$type") {
                'payable' => 'XPP',
                'receivable' => 'XRP',
                default => 'XDP', // generic fallback
            };

            $yearTwoDigits = date('y', strtotime($model->payment_date ?: now()));
            $paddedBranchId = str_pad($model->branch_id, 3, '0', STR_PAD_LEFT);

            $last = self::query()
                ->when($type, function ($q) use ($type) {
                    // Match by prefix to maintain independent sequences per type
                    $q->where('number', 'like', ($type === 'payable' ? 'XPP' : 'XRP') . '.%');
                })
                ->whereYear('payment_date', date('Y', strtotime($model->payment_date ?: now())))
                ->where('branch_id', $model->branch_id)
                ->withTrashed()
                ->orderBy('number', 'desc')
                ->first();

            $lastNumber = $last ? intval(substr($last->number, -5)) : 0;
            $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);

            $model->number = $prefix . '.' . $yearTwoDigits . '.' . $paddedBranchId . '.' . $newNumber;

            if (Auth::check() && !$model->created_by) {
                $model->created_by = Auth::user()->global_id;
            }
        });

        static::updating(function ($model) {
            if (Auth::check() && !$model->updated_by) {
                $model->updated_by = Auth::user()->global_id;
            }
        });
    }
    
    public function details()
    {
        return $this->hasMany(ExternalDebtPaymentDetail::class);
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

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'global_id');
    }
}


