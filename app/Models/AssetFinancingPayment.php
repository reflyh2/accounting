<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AvoidDuplicateConstraintOnSoftDelete;

class AssetFinancingPayment extends Model
{
    use HasFactory, SoftDeletes, AvoidDuplicateConstraintOnSoftDelete;

    protected $guarded = ['id'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $lastPayment = self::orderBy('number', 'desc')->first();
            $lastNumber = $lastPayment ? intval(substr($lastPayment->number, -5)) : 0;
            $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
            $model->number = 'AFP.' . date('ym') . '.' . $newNumber;
        });
    }

    public function getDuplicateAvoidColumns(): array
    {
        return ['number'];
    }

    public function creditor()
    {
        return $this->belongsTo(Partner::class, 'creditor_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function allocations()
    {
        return $this->hasMany(AssetFinancingPaymentAllocation::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by', 'global_id');
    }

    public static function getPaymentMethods()
    {
        return [
            'cash' => 'Tunai',
            'check' => 'Cek',
            'credit_card' => 'Kartu Kredit',
            'bank_transfer' => 'Transfer Bank',
            'other' => 'Lainnya',
        ];
    }

    public function sourceAccount()
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    public function destinationBankAccount()
    {
        return $this->belongsTo(PartnerBankAccount::class, 'destination_bank_account_id');
    }
} 