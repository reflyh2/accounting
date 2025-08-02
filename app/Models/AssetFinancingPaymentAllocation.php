<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetFinancingPaymentAllocation extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function assetFinancingPayment()
    {
        return $this->belongsTo(AssetFinancingPayment::class);
    }

    public function assetFinancingAgreement()
    {
        return $this->belongsTo(AssetFinancingAgreement::class);
    }

    public function assetFinancingSchedule()
    {
        return $this->belongsTo(AssetFinancingSchedule::class);
    }
} 