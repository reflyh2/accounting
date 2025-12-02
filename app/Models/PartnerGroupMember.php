<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PartnerGroupMember extends Model
{
    protected $guarded = [];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function scopeActive($query, ?Carbon $date = null)
    {
        $date = $date ?? now();

        return $query->where('status', 'active')
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', $date);
            });
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function partnerGroup()
    {
        return $this->belongsTo(PartnerGroup::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}

