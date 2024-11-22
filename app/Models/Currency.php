<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'is_primary',
    ];
    
    public function companyRates()
    {
        return $this->hasMany(CompanyCurrencyRate::class);
    }

    public function companies()
    {
        return $this->belongsToManyThrough(Company::class, CompanyCurrencyRate::class);
    }

    public function accounts()
    {
        return $this->belongsToMany(Account::class, 'account_currencies')
            ->withPivot('balance');
    }
}
