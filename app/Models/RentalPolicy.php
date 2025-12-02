<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentalPolicy extends Model
{
    protected $guarded = [];

    protected $casts = [
        'late_fee_rule' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

