<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PartnerBankAccount extends Model
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
                static::where('partner_id', $model->partner_id)
                    ->where('id', '!=', $model->id)
                    ->update(['is_primary' => false]);
            }
        });

        static::updating(function ($model) {
            // If this is set as primary, remove primary from other accounts
            if ($model->is_primary && $model->isDirty('is_primary')) {
                static::where('partner_id', $model->partner_id)
                    ->where('id', '!=', $model->id)
                    ->update(['is_primary' => false]);
            }
        });
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function getDisplayNameAttribute()
    {
        return "{$this->bank_name} - {$this->account_number} ({$this->account_holder_name})";
    }
} 