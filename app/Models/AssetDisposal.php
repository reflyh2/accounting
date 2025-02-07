<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDisposal extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'asset_id',
        'disposal_date',
        'disposal_method',
        'disposal_amount',
        'book_value_at_disposal',
        'gain_loss_amount',
        'status',
        'requested_by',
        'approved_by',
        'reason',
        'notes'
    ];

    protected $casts = [
        'disposal_date' => 'date',
        'disposal_amount' => 'decimal:2',
        'book_value_at_disposal' => 'decimal:2',
        'gain_loss_amount' => 'decimal:2',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
} 