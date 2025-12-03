<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'partner_id' => ['required', 'exists:partners,id'],
            'price_list_id' => ['nullable', 'exists:price_lists,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'quote_valid_until' => ['nullable', 'date'],
            'customer_reference' => ['nullable', 'string', 'max:120'],
            'sales_channel' => ['nullable', 'string', 'max:120'],
            'payment_terms' => ['nullable', 'string', 'max:120'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.0001'],
            'reserve_stock' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'lines.*.uom_id' => ['required', 'exists:uoms,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.requested_delivery_date' => ['nullable', 'date'],
            'lines.*.reservation_location_id' => [
                'nullable',
                'exists:locations,id',
                'required_if:reserve_stock,1',
            ],
        ];
    }
}


