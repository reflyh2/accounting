<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssetMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'maintenance_date' => 'required|date',
            'maintenance_type' => 'required|in:repair,service,inspection,upgrade,other',
            'description' => 'required|string|max:2000',
            'vendor_id' => 'nullable|exists:partners,id',
            'labor_cost' => 'nullable|numeric|min:0',
            'parts_cost' => 'nullable|numeric|min:0',
            'external_cost' => 'nullable|numeric|min:0',
            'status' => 'required|in:draft,completed,cancelled',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function attributes(): array
    {
        return [
            'maintenance_date' => 'tanggal pemeliharaan',
            'maintenance_type' => 'jenis pemeliharaan',
            'description' => 'deskripsi',
            'vendor_id' => 'vendor',
            'labor_cost' => 'biaya tenaga kerja',
            'parts_cost' => 'biaya suku cadang',
            'external_cost' => 'biaya eksternal',
            'status' => 'status',
            'notes' => 'catatan',
        ];
    }
}
