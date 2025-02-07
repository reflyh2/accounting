<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('asset_categories')->ignore($this->route('assetCategory')),
            ],
            'description' => 'nullable|string',
            'company_ids' => 'required|array',
            'company_ids.*' => 'exists:companies,id',
            'fixed_asset_account_id' => 'required|exists:accounts,id',
            'purchase_payable_account_id' => 'required|exists:accounts,id',
            'accumulated_depreciation_account_id' => 'required|exists:accounts,id',
            'depreciation_expense_account_id' => 'required|exists:accounts,id',
            'prepaid_rent_account_id' => 'required|exists:accounts,id',
            'rent_expense_account_id' => 'required|exists:accounts,id',
            'create_another' => 'boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama kategori',
            'description' => 'deskripsi',
            'company_ids' => 'perusahaan',
            'company_ids.*' => 'perusahaan',
            'fixed_asset_account_id' => 'akun aset tetap',
            'purchase_payable_account_id' => 'akun hutang pembelian',
            'accumulated_depreciation_account_id' => 'akun akumulasi penyusutan',
            'depreciation_expense_account_id' => 'akun beban penyusutan',
            'prepaid_rent_account_id' => 'akun sewa dibayar dimuka',
            'rent_expense_account_id' => 'akun beban sewa',
        ];
    }
}
