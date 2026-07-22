<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingLineSupplierCostRequest extends FormRequest
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
            'supplier_cost' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_cost.required' => 'Harga supplier wajib diisi.',
            'supplier_cost.numeric' => 'Harga supplier harus berupa angka.',
            'supplier_cost.min' => 'Harga supplier tidak boleh kurang dari nol.',
        ];
    }
}
