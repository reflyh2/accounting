<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResourceInstance extends Model
{
    protected $guarded = [];

    protected $casts = [
        'attrs_json' => 'array',
    ];

    public function pool()
    {
        return $this->belongsTo(ResourcePool::class, 'resource_pool_id');
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function bookingLines()
    {
        return $this->hasMany(BookingLine::class);
    }
}

