<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvailabilityRule extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
    ];

    public function pool()
    {
        return $this->belongsTo(ResourcePool::class, 'resource_pool_id');
    }

    public function instance()
    {
        return $this->belongsTo(ResourceInstance::class, 'resource_instance_id');
    }
}

