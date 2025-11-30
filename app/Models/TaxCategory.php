<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxCategory extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'attributes_json' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function taxRules()
    {
        return $this->hasMany(TaxRule::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}


