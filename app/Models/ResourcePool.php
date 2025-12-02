<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResourcePool extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function instances()
    {
        return $this->hasMany(ResourceInstance::class);
    }

    public function activeInstances()
    {
        return $this->instances()->where('status', 'active');
    }

    public function availabilityRules()
    {
        return $this->hasMany(AvailabilityRule::class);
    }

    public function occurrences()
    {
        return $this->hasMany(Occurrence::class);
    }

    public function bookingLines()
    {
        return $this->hasMany(BookingLine::class);
    }
}

