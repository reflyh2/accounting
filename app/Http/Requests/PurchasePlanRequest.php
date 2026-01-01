<?php

namespace App\Http\Requests;

use App\Enums\Documents\PurchasePlanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchasePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'exists:branches,id'],
            'plan_date' => ['required', 'date'],
            'required_date' => ['nullable', 'date', 'after_or_equal:plan_date'],
            'source_type' => ['nullable', 'string', 'max:100'],
            'source_ref_id' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'lines.*.uom_id' => ['required', 'exists:uoms,id'],
            'lines.*.planned_qty' => ['required', 'numeric', 'min:0.001'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.required_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.required' => 'Cabang wajib dipilih.',
            'plan_date.required' => 'Tanggal rencana wajib diisi.',
            'lines.required' => 'Minimal satu baris item wajib diisi.',
            'lines.*.product_id.required' => 'Produk wajib dipilih.',
            'lines.*.uom_id.required' => 'Satuan wajib dipilih.',
            'lines.*.planned_qty.required' => 'Kuantitas wajib diisi.',
            'lines.*.planned_qty.min' => 'Kuantitas minimal 0.001.',
        ];
    }
}
