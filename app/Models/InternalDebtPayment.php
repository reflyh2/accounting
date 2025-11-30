<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use App\Enums\PaymentStatus;
use App\Enums\PaymentMethod;

class InternalDebtPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'float',
        'primary_currency_amount' => 'float',
        'payment_date' => 'date',
        'status' => PaymentStatus::class,
        'payment_method' => PaymentMethod::class,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // type must be provided: 'payable' | 'receivable'
            // Generate payment number with prefix based on type:
            // IPP = Internal Payable Payment, IRP = Internal Receivable Payment
            // Format: IPP|IRP.YY.BBB.NNNNN
            $type = $model->type ?? null;
            $prefix = match ((string) $type) {
                'payable' => 'IPP',
                'receivable' => 'IRP',
                default => 'IDP', // generic fallback
            };

            $yearTwoDigits = date('y', strtotime($model->payment_date ?: now()));
            $paddedBranchId = str_pad($model->branch_id, 3, '0', STR_PAD_LEFT);

            $last = self::query()
                ->when($type, function ($q) use ($type) {
                    $q->where('number', 'like', ($type === 'payable' ? 'IPP' : 'IRP') . '.%');
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

    public static function statusStyles(): array
    {
        return PaymentStatus::styles();
    }

    public static function statusOptions(): array
    {
        return PaymentStatus::options();
    }

    public static function paymentMethodStyles(): array
    {
        return PaymentMethod::styles();
    }

    public static function paymentMethodOptions(): array
    {
        return PaymentMethod::options();
    }

    public function details()
    {
        return $this->hasMany(InternalDebtPaymentDetail::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
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
}


