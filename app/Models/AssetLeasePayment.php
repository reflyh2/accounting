<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetLeasePayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'asset_lease_id',
        'due_date',
        'payment_date',
        'amount',
        'interest_portion',
        'principal_portion',
        'status',
        'notes'
    ];

    protected $casts = [
        'due_date' => 'date',
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'interest_portion' => 'decimal:2',
        'principal_portion' => 'decimal:2',
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(AssetLease::class, 'asset_lease_id');
    }
} 