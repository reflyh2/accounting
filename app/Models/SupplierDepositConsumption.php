<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SupplierDepositConsumption extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_base' => 'decimal:4',
        'consumed_at' => 'datetime',
    ];

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(SupplierDeposit::class, 'supplier_deposit_id');
    }

    /**
     * The source row that consumed the deposit (BookingLine or SalesInvoiceCost).
     */
    public function consumedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }
}
