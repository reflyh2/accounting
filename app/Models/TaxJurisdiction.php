<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxJurisdiction extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'attributes_json' => 'array',
    ];

    public function parent()
    {
        return $this->belongsTo(TaxJurisdiction::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(TaxJurisdiction::class, 'parent_id');
    }

    public function components()
    {
        return $this->hasMany(TaxComponent::class, 'tax_jurisdiction_id');
    }

    public function taxRules()
    {
        return $this->hasMany(TaxRule::class, 'tax_jurisdiction_id');
    }
}


