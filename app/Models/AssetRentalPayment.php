<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssetRentalPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_id',
        'period_start',
        'period_end',
        'payment_date',
        'amount',
        'status',
        'notes',
        'credited_account_id',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function creditedAccount()
    {
        return $this->belongsTo(Account::class, 'credited_account_id');
    }
} 