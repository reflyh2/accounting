<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssetInvoiceDetail extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Disable timestamps if they are not needed or handled differently
    // public $timestamps = false; 

    public function assetInvoice()
    {
        return $this->belongsTo(AssetInvoice::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
} 