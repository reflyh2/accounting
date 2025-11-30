<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxComponent extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'attributes_json' => 'array',
    ];

    public function jurisdiction()
    {
        return $this->belongsTo(TaxJurisdiction::class, 'tax_jurisdiction_id');
    }

    public function taxRules()
    {
        return $this->hasMany(TaxRule::class);
    }
}


