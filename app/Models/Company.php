<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'tax_id',
        'business_registration_number',
        'address',
        'city',
        'province',
        'postal_code',
        'phone',
        'email',
        'website',
        'industry',
        'year_established',
        'business_license_number',
        'business_license_expiry',
        'tax_registration_number',
        'social_security_number',
        'default_receivable_account_id',
        'default_payable_account_id',
        'default_revenue_account_id',
        'default_cogs_account_id',
        'default_retained_earnings_account_id',
        'default_interbranch_receivable_account_id',
        'default_interbranch_payable_account_id',
        'default_intercompany_receivable_account_id',
        'default_intercompany_payable_account_id',
    ];

    protected $casts = [
        'year_established' => 'integer',
        'business_license_expiry' => 'date',
    ];

    protected static function booted()
    {
        static::addGlobalScope('userCompanies', function ($builder) {
            if (Auth::check()) {
                $userId = Auth::user()->global_id;
                $builder->whereHas('branchGroups.branches.users', function ($query) use ($userId) {
                    $query->where('users.global_id', $userId);
                });
            }
        });
    }

    public function branchGroups()
    {
        return $this->hasMany(BranchGroup::class);
    }

    public function branchGroupsAll()
    {
        return $this->hasMany(BranchGroup::class)->withoutGlobalScope('userBranchGroups');
    }

    public function branches()
    {
        return $this->hasManyThrough(Branch::class, BranchGroup::class);
    }

    public function accounts()
    {
        return $this->belongsToMany(Account::class);
    }

    public function currencyRates()
    {
        return $this->hasMany(CompanyCurrencyRate::class);
    }

    public function currencies()
    {
        return $this->belongsToManyThrough(Currency::class, CompanyCurrencyRate::class);
    }

    public function assetCategories()
    {
        return $this->belongsToMany(AssetCategory::class, 'asset_category_company')
            ->withPivot([
                'asset_account_id',
                'asset_depreciation_account_id',
                'asset_accumulated_depreciation_account_id',
                'asset_amortization_account_id',
                'asset_prepaid_amortization_account_id',
                'asset_rental_cost_account_id',
            ])
            ->withTimestamps();
    }

    public function defaultReceivableAccount()
    {
        return $this->belongsTo(Account::class, 'default_receivable_account_id');
    }

    public function defaultPayableAccount()
    {
        return $this->belongsTo(Account::class, 'default_payable_account_id');
    }

    public function defaultRevenueAccount()
    {
        return $this->belongsTo(Account::class, 'default_revenue_account_id');
    }

    public function defaultCogsAccount()
    {
        return $this->belongsTo(Account::class, 'default_cogs_account_id');
    }

    public function defaultRetainedEarningsAccount()
    {
        return $this->belongsTo(Account::class, 'default_retained_earnings_account_id');
    }

    public function defaultInterbranchReceivableAccount()
    {
        return $this->belongsTo(Account::class, 'default_interbranch_receivable_account_id');
    }

    public function defaultInterbranchPayableAccount()
    {
        return $this->belongsTo(Account::class, 'default_interbranch_payable_account_id');
    }

    public function defaultIntercompanyReceivableAccount()
    {
        return $this->belongsTo(Account::class, 'default_intercompany_receivable_account_id');
    }

    public function defaultIntercompanyPayableAccount()
    {
        return $this->belongsTo(Account::class, 'default_intercompany_payable_account_id');
    }
}
