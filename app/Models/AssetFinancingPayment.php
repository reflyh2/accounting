<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssetFinancingPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_id',
        'due_date',
        'payment_date',
        'amount',
        'principal_portion',
        'interest_portion',
        'status',
        'notes',
        'credited_account_id',
    ];

    protected $casts = [
        'due_date' => 'date',
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'principal_portion' => 'decimal:2',
        'interest_portion' => 'decimal:2',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function creditedAccount()
    {
        return $this->belongsTo(Account::class, 'credited_account_id');
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }
} 