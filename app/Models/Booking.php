<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $guarded = [];

    protected $casts = [
        'booked_at' => 'datetime',
        'held_until' => 'datetime',
        'deposit_amount' => 'decimal:2',
    ];

    public function lines()
    {
        return $this->hasMany(BookingLine::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
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

