<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssetInvoicePaymentAllocation extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function assetInvoicePayment()
    {
        return $this->belongsTo(AssetInvoicePayment::class);
    }

    public function assetInvoice()
    {
        return $this->belongsTo(AssetInvoice::class);
    }
} 