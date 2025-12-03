<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sales_order_id' => ['required', 'exists:sales_orders,id'],
            'delivery_date' => ['required', 'date'],
            'location_id' => ['required', 'exists:locations,id'],
            'notes' => ['nullable', 'string'],
            'valuation_method' => ['nullable', 'in:fifo,moving_avg'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sales_order_line_id' => ['required', 'exists:sales_order_lines,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}


