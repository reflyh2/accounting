<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssetMaintenance extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'asset_maintenance_records';

    protected $fillable = [
        'asset_id',
        'maintenance_date',
        'type',
        'performed_by',
        'cost',
        'description',
        'notes',
    ];

    protected $casts = [
        'maintenance_date' => 'date',
        'cost' => 'decimal:2',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
} 