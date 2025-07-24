<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetFinancingSchedule extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function agreement()
    {
        return $this->belongsTo(AssetFinancingAgreement::class, 'asset_financing_agreement_id');
    }
}
