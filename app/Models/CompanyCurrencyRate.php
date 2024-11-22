<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyCurrencyRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'currency_id',
        'company_id',
        'exchange_rate',
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
