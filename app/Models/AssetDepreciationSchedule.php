<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetDepreciationSchedule extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'schedule_date' => 'date',
        'is_processed' => 'boolean',
        'processed_at' => 'datetime',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }
}

