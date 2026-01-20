<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CostPoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $poolId = $this->route('cost_pool')?->id;

        $uniqueCodeRule = Rule::unique('cost_pools', 'code')
            ->where('company_id', $this->company_id);

        if ($poolId) {
            $uniqueCodeRule->ignore($poolId);
        }

        return [
            'company_id' => ['required', 'exists:companies,id'],
            'code' => [
                'required',
                'string',
                'max:50',
                $uniqueCodeRule,
            ],
            'name' => ['required', 'string', 'max:255'],
            'pool_type' => ['required', 'string', 'in:asset,service,branch'],
            'allocation_rule' => ['nullable', 'string', 'in:revenue_based,quantity_based,time_based,manual'],
            'asset_id' => ['nullable', 'exists:assets,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Kode pool sudah digunakan untuk perusahaan ini.',
            'company_id.required' => 'Perusahaan wajib dipilih.',
            'code.required' => 'Kode pool wajib diisi.',
            'name.required' => 'Nama pool wajib diisi.',
            'pool_type.required' => 'Tipe pool wajib dipilih.',
        ];
    }
}
