<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingLineResource extends Model
{
    protected $guarded = [];

    public function bookingLine()
    {
        return $this->belongsTo(BookingLine::class);
    }

    public function resourceInstance()
    {
        return $this->belongsTo(ResourceInstance::class);
    }
}

