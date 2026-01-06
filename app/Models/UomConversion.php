<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UomConversion extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'numerator' => 'integer',
        'denominator' => 'integer',
        'factor' => 'decimal:6',
    ];

    public function fromUom()
    {
        return $this->belongsTo(Uom::class, 'from_uom_id');
    }

    public function toUom()
    {
        return $this->belongsTo(Uom::class, 'to_uom_id');
    }
}



