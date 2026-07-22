<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingDepositRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'company_bank_account_id' => ['nullable', 'exists:company_bank_accounts,id'],
            'received_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
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
            'amount.required' => 'Jumlah deposit wajib diisi.',
            'amount.numeric' => 'Jumlah deposit harus berupa angka.',
            'amount.min' => 'Jumlah deposit tidak boleh kurang dari 0.01.',
            'payment_method.max' => 'Metode pembayaran tidak boleh lebih dari 30 karakter.',
            'company_bank_account_id.exists' => 'Rekening bank tidak valid.',
            'received_at.required' => 'Tanggal terima wajib diisi.',
            'received_at.date' => 'Tanggal terima harus berupa format tanggal yang valid.',
        ];
    }
}
