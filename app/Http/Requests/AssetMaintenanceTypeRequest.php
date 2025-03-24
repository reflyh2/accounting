<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetMaintenanceTypeRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'asset_category_id' => 'required|exists:asset_categories,id',
            'maintenance_cost_account_id' => 'required|exists:accounts,id',
            'description' => 'nullable|string',
            'maintenance_interval' => 'nullable|string|max:100',
            'maintenance_interval_days' => 'nullable|integer|min:1|max:3650',
            'company_ids' => 'required|array',
            'company_ids.*' => 'exists:companies,id',
            'create_another' => 'boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama tipe pemeliharaan',
            'asset_category_id' => 'kategori aset',
            'maintenance_cost_account_id' => 'akun biaya pemeliharaan',
            'description' => 'deskripsi',
            'maintenance_interval' => 'interval pemeliharaan',
            'maintenance_interval_days' => 'interval pemeliharaan (hari)',
            'company_ids' => 'perusahaan',
            'company_ids.*' => 'perusahaan',
        ];
    }
} 