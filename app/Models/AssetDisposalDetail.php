<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssetDisposalDetail extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function assetDisposal()
    {
        return $this->belongsTo(AssetDisposal::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
} 