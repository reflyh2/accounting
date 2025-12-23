<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CompanyBankAccount extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // If this is set as primary, remove primary from other accounts
            if ($model->is_primary) {
                static::where('company_id', $model->company_id)
                    ->where('id', '!=', $model->id ?? 0)
                    ->update(['is_primary' => false]);
            }
        });

        static::updating(function ($model) {
            // If this is set as primary, remove primary from other accounts
            if ($model->is_primary && $model->isDirty('is_primary')) {
                static::where('company_id', $model->company_id)
                    ->where('id', '!=', $model->id)
                    ->update(['is_primary' => false]);
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function getDisplayNameAttribute()
    {
        return "{$this->bank_name} - {$this->account_number} ({$this->account_holder_name})";
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
