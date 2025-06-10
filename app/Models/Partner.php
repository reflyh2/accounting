<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class Partner extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $lastPartner = self::withTrashed()->orderBy('id', 'desc')->first();
            $lastCode = $lastPartner ? intval(substr($lastPartner->code, 3)) : 0;
            $newCode = str_pad($lastCode + 1, 4, '0', STR_PAD_LEFT);
            $model->code = 'PTR' . $newCode;

            if (Auth::check()) {
                $model->created_by = Auth::user()->global_id;
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::user()->global_id;
            }
        });
    }

    public function roles()
    {
        return $this->hasMany(PartnerRole::class);
    }

    public function contacts()
    {
        return $this->hasMany(PartnerContact::class);
    }

    public function bankAccounts()
    {
        return $this->hasMany(PartnerBankAccount::class);
    }

    public function primaryBankAccount()
    {
        return $this->hasOne(PartnerBankAccount::class)->where('is_primary', true);
    }

    public function activeBankAccounts()
    {
        return $this->hasMany(PartnerBankAccount::class)->where('is_active', true);
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'partner_company');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by', 'global_id');
    }

    // Helper methods to check roles
    public function isSupplier()
    {
        return $this->roles()->where('role', 'supplier')->exists();
    }

    public function isCustomer()
    {
        return $this->roles()->where('role', 'customer')->exists();
    }

    public function isCreditor()
    {
        return $this->roles()->where('role', 'creditor')->exists();
    }

    public static function getRoles()
    {
        return [
            'supplier' => 'Pemasok',
            'customer' => 'Pelanggan',
            'asset_supplier' => 'Pemasok Aset',
            'asset_customer' => 'Pelanggan Aset',
            'creditor' => 'Kreditor',
            'others' => 'Lainnya'
        ];
    }
} 