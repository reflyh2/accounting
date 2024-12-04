<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMaintenanceRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'asset_id',
        'maintenance_date',
        'maintenance_type',
        'cost',
        'description',
        'performed_by',
        'next_maintenance_date',
        'completed_at',
    ];

    protected $casts = [
        'maintenance_date' => 'date',
        'next_maintenance_date' => 'date',
        'cost' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
} 