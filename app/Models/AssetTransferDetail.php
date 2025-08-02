<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetTransferDetail extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function assetTransfer()
    {
        return $this->belongsTo(AssetTransfer::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
} 