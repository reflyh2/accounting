<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AvoidDuplicateConstraintOnSoftDelete;

class AssetCategory extends Model
{
    use HasFactory, SoftDeletes, AvoidDuplicateConstraintOnSoftDelete;

    protected $guarded = [];

    public function getDuplicateAvoidColumns(): array
    {
        return ['code'];
    }

    /**
     * The companies that belong to the asset category.
     */
    public function companies()
    {
        return $this->belongsToMany(Company::class, 'asset_category_company')
            ->withPivot([
                'asset_account_id',
                'asset_depreciation_account_id',
                'asset_accumulated_depreciation_account_id',
                'asset_amortization_account_id',
                'asset_prepaid_amortization_account_id',
                'asset_rental_cost_account_id',
                'asset_acquisition_payable_account_id',
                'asset_sale_receivable_account_id',
                'asset_financing_payable_account_id',
                'asset_sale_profit_account_id',
                'asset_sale_loss_account_id',
            ])
            ->withTimestamps();
    }

    /**
     * Get the asset account for a specific company.
     */
    public function assetAccount(Company $company)
    {
        return Account::find($this->companies()->where('company_id', $company->id)->first()?->pivot?->asset_account_id);
    }

    /**
     * Get the asset depreciation account for a specific company.
     */
    public function assetDepreciationAccount(Company $company)
    {
        return Account::find($this->companies()->where('company_id', $company->id)->first()?->pivot?->asset_depreciation_account_id);
    }

    /**
     * Get the asset accumulated depreciation account for a specific company.
     */
    public function assetAccumulatedDepreciationAccount(Company $company)
    {
        return Account::find($this->companies()->where('company_id', $company->id)->first()?->pivot?->asset_accumulated_depreciation_account_id);
    }

    /**
     * Get the asset amortization account for a specific company.
     */
    public function assetAmortizationAccount(Company $company)
    {
        return Account::find($this->companies()->where('company_id', $company->id)->first()?->pivot?->asset_amortization_account_id);
    }

    /**
     * Get the asset prepaid amortization account for a specific company.
     */
    public function assetPrepaidAmortizationAccount(Company $company)
    {
        return Account::find($this->companies()->where('company_id', $company->id)->first()?->pivot?->asset_prepaid_amortization_account_id);
    }

    /**
     * Get the asset rental cost account for a specific company.
     */
    public function assetRentalCostAccount(Company $company)
    {
        return Account::find($this->companies()->where('company_id', $company->id)->first()?->pivot?->asset_rental_cost_account_id);
    }

    /**
     * Get the asset acquisition payable account for a specific company.
     */
    public function assetAcquisitionPayableAccount(Company $company)
    {
        return Account::find($this->companies()->where('company_id', $company->id)->first()?->pivot?->asset_acquisition_payable_account_id);
    }

    /**
     * Get the asset sale receivable account for a specific company.
     */
    public function assetSaleReceivableAccount(Company $company)
    {
        return Account::find($this->companies()->where('company_id', $company->id)->first()?->pivot?->asset_sale_receivable_account_id);
    }

    /**
     * Get the asset financing payable account for a specific company.
     */
    public function assetFinancingPayableAccount(Company $company)
    {
        return Account::find($this->companies()->where('company_id', $company->id)->first()?->pivot?->asset_financing_payable_account_id);
    }

    /**
     * Get the asset sale profit account for a specific company.
     */
    public function assetSaleProfitAccount(Company $company)
    {
        return Account::find($this->companies()->where('company_id', $company->id)->first()?->pivot?->asset_sale_profit_account_id);
    }

    /**
     * Get the asset sale loss account for a specific company.
     */
    public function assetSaleLossAccount(Company $company)
    {
        return Account::find($this->companies()->where('company_id', $company->id)->first()?->pivot?->asset_sale_loss_account_id);
    }

} 