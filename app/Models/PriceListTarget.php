<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceListTarget extends Model
{
    protected $guarded = [];

    protected $casts = [
        'priority' => 'integer',
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function priceList()
    {
        return $this->belongsTo(PriceList::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function partnerGroup()
    {
        return $this->belongsTo(PartnerGroup::class);
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

