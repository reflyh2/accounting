<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'transaction_date' => 'date',
    ];

    public function locationFrom()
    {
        return $this->belongsTo(Location::class, 'location_id_from');
    }

    public function locationTo()
    {
        return $this->belongsTo(Location::class, 'location_id_to');
    }

    public function lines()
    {
        return $this->hasMany(InventoryTransactionLine::class);
    }
}


