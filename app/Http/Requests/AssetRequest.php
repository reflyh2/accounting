<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssetRequest extends FormRequest
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
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:asset_categories,id',
            'asset_type' => 'required|in:tangible,intangible',
            'acquisition_type' => 'required|in:outright_purchase,financed_purchase,fixed_rental,periodic_rental,casual_rental',
            'serial_number' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive,maintenance,disposed',
            'purchase_cost' => 'required_if:acquisition_type,outright_purchase,financed_purchase|nullable|numeric|min:0',
            'purchase_date' => 'required_if:acquisition_type,outright_purchase,financed_purchase|nullable|date',
            'supplier' => 'nullable|string|max:255',
            'warranty_expiry' => 'nullable|date',
            'location' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'notes' => 'nullable|string',

            // Depreciation fields (required for outright_purchase and financed_purchase)
            'depreciation_method' => 'required_if:acquisition_type,outright_purchase,financed_purchase|nullable|in:straight-line,declining-balance',
            'useful_life_months' => 'required_if:acquisition_type,outright_purchase,financed_purchase|nullable|integer|min:1',
            'salvage_value' => 'required_if:acquisition_type,outright_purchase,financed_purchase|nullable|numeric|min:0',
            'first_depreciation_date' => 'required_if:acquisition_type,outright_purchase,financed_purchase|nullable|date',

            // Financing fields (required for financed_purchase)
            'down_payment' => 'required_if:acquisition_type,financed_purchase|nullable|numeric|min:0',
            'financing_amount' => 'required_if:acquisition_type,financed_purchase|nullable|numeric|min:0',
            'interest_rate' => 'required_if:acquisition_type,financed_purchase|nullable|numeric|min:0',
            'financing_term_months' => 'required_if:acquisition_type,financed_purchase|nullable|integer|min:1',
            'first_payment_date' => 'required_if:acquisition_type,financed_purchase|nullable|date',

            // Rental fields
            'rental_start_date' => 'required_if:acquisition_type,fixed_rental,periodic_rental|nullable|date',
            'rental_end_date' => 'required_if:acquisition_type,fixed_rental,periodic_rental|nullable|date|after:rental_start_date',
            'rental_amount' => 'required_if:acquisition_type,fixed_rental,periodic_rental|nullable|numeric|min:0',
            'rental_terms' => 'nullable|string',
            'payment_frequency' => 'required_if:acquisition_type,periodic_rental|nullable|in:monthly,quarterly,annually',

            // Amortization fields (required for fixed_rental)
            'amortization_term_months' => 'required_if:acquisition_type,fixed_rental|nullable|integer|min:1',
            'first_amortization_date' => 'required_if:acquisition_type,fixed_rental|nullable|date',

            // Revaluation fields
            'revaluation_method' => 'nullable|string|max:255',
            'last_revaluation_date' => 'nullable|date',
            'last_revaluation_amount' => 'nullable|numeric|min:0',
            'revaluation_notes' => 'nullable|string',

            // Impairment fields
            'is_impaired' => 'boolean',
            'impairment_amount' => 'required_if:is_impaired,true|nullable|numeric|min:0',
            'impairment_date' => 'required_if:is_impaired,true|nullable|date',
            'impairment_notes' => 'nullable|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Kolom :attribute wajib diisi.',
            'required_if' => 'Kolom :attribute wajib diisi untuk jenis perolehan yang dipilih.',
            'numeric' => 'Kolom :attribute harus berupa angka.',
            'min' => 'Kolom :attribute minimal :min.',
            'date' => 'Kolom :attribute harus berupa tanggal yang valid.',
            'after' => 'Kolom :attribute harus setelah tanggal mulai sewa.',
            'exists' => 'Pilihan :attribute tidak valid.',
            'in' => 'Pilihan :attribute tidak valid.',
            'string' => 'Kolom :attribute harus berupa teks.',
            'max' => 'Kolom :attribute maksimal :max karakter.',
            'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
            'boolean' => 'Kolom :attribute harus berupa nilai boolean.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'branch_id' => 'Cabang',
            'name' => 'Nama',
            'category_id' => 'Kategori',
            'asset_type' => 'Jenis Aset',
            'acquisition_type' => 'Jenis Perolehan',
            'serial_number' => 'Nomor Seri',
            'status' => 'Status',
            'purchase_cost' => 'Harga Perolehan',
            'purchase_date' => 'Tanggal Perolehan',
            'supplier' => 'Pemasok',
            'warranty_expiry' => 'Masa Garansi',
            'location' => 'Lokasi',
            'department' => 'Departemen',
            'notes' => 'Catatan',
            'depreciation_method' => 'Metode Penyusutan',
            'useful_life_months' => 'Masa Manfaat',
            'salvage_value' => 'Nilai Sisa',
            'first_depreciation_date' => 'Tanggal Penyusutan Pertama',
            'down_payment' => 'Uang Muka',
            'financing_amount' => 'Jumlah Pembiayaan',
            'interest_rate' => 'Suku Bunga',
            'financing_term_months' => 'Jangka Waktu Pembiayaan',
            'first_payment_date' => 'Tanggal Pembayaran Pertama',
            'rental_start_date' => 'Tanggal Mulai Sewa',
            'rental_end_date' => 'Tanggal Selesai Sewa',
            'rental_amount' => 'Biaya Sewa',
            'rental_terms' => 'Ketentuan Sewa',
            'payment_frequency' => 'Frekuensi Pembayaran',
            'amortization_term_months' => 'Masa Amortisasi',
            'first_amortization_date' => 'Tanggal Mulai Amortisasi',
            'revaluation_method' => 'Metode Revaluasi',
            'last_revaluation_date' => 'Tanggal Revaluasi Terakhir',
            'last_revaluation_amount' => 'Nilai Revaluasi Terakhir',
            'revaluation_notes' => 'Catatan Revaluasi',
            'is_impaired' => 'Mengalami Penurunan Nilai',
            'impairment_amount' => 'Jumlah Penurunan Nilai',
            'impairment_date' => 'Tanggal Penurunan Nilai',
            'impairment_notes' => 'Catatan Penurunan Nilai',
        ];
    }
} 