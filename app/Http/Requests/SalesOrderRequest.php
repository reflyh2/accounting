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
            'shipping_address_id' => ['nullable', 'exists:partner_addresses,id'],
            'invoice_address_id' => ['nullable', 'exists:partner_addresses,id'],
            'shipping_type' => ['nullable', 'string', 'in:internal,external'],
            'shipping_provider_id' => ['nullable', 'exists:shipping_providers,id', 'required_with:shipping_type'],
            'estimated_shipping_charge' => ['nullable', 'numeric', 'min:0'],
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
            'payment_method' => ['nullable', 'string', 'in:cash,transfer,cek,giro,credit_card,qris,paypal,midtrans'],
            'company_bank_account_id' => ['nullable', 'exists:company_bank_accounts,id', 'required_if:payment_method,transfer'],
            'sales_person_id' => ['nullable', 'exists:users,global_id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'lines.*.uom_id' => ['required', 'exists:uoms,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.requested_delivery_date' => ['nullable', 'date'],
            'lines.*.reservation_location_id' => [
                'nullable',
                'exists:locations,id',
                'required_if:reserve_stock,1',
            ],
            'costs' => ['nullable', 'array'],
            'costs.*.description' => ['nullable', 'string', 'max:255'],
            'costs.*.cost_item_id' => ['nullable', 'exists:cost_items,id'],
            'costs.*.amount' => ['required', 'numeric', 'min:0'],
            'costs.*.currency_id' => ['nullable', 'exists:currencies,id'],
            'costs.*.exchange_rate' => ['nullable', 'numeric', 'min:0.0001'],
        ];
    }
}
