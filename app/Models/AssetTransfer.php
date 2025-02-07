<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetTransfer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'asset_id',
        'from_department',
        'to_department',
        'from_location',
        'to_location',
        'transfer_date',
        'status',
        'requested_by',
        'approved_by',
        'reason',
        'notes'
    ];

    protected $casts = [
        'transfer_date' => 'date',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
} 