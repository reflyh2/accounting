<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssetDepreciationEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_id',
        'entry_date',
        'type',
        'status',
        'amount',
        'cumulative_amount',
        'remaining_value',
        'journal_id',
        'period_start',
        'period_end',
        'notes',
        'debit_account_id',
        'credit_account_id',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'amount' => 'decimal:2',
        'cumulative_amount' => 'decimal:2',
        'remaining_value' => 'decimal:2',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function debitAccount()
    {
        return $this->belongsTo(Account::class, 'debit_account_id');
    }

    public function creditAccount()
    {
        return $this->belongsTo(Account::class, 'credit_account_id');
    }
    
} 