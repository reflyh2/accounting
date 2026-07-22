<?php

namespace App\Http\Requests;

use App\Models\AccountingPeriod;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreAccountingPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $companyId = $this->input('company_id');

        if (is_array($companyId)) {
            $companyId = reset($companyId);
        }

        $this->merge([
            'company_id' => $companyId ? (int) $companyId : null,
            'month' => $this->input('month') ? (int) $this->input('month') : null,
            'year' => $this->input('year') ? (int) $this->input('year') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $companyId = $this->input('company_id');
            $month = $this->input('month');
            $year = $this->input('year');

            if ($companyId && $month && $year) {
                $startDate = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfMonth()->toDateString();
                $endDate = Carbon::createFromDate((int) $year, (int) $month, 1)->endOfMonth()->toDateString();

                $overlap = AccountingPeriod::where('company_id', $companyId)
                    ->where(function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('start_date', [$startDate, $endDate])
                            ->orWhereBetween('end_date', [$startDate, $endDate])
                            ->orWhere(function ($q) use ($startDate, $endDate) {
                                $q->where('start_date', '<=', $startDate)
                                    ->where('end_date', '>=', $endDate);
                            });
                    })
                    ->exists();

                if ($overlap) {
                    $validator->errors()->add('month', 'Periode untuk bulan dan tahun ini sudah dibuat sebelumnya.');
                }
            }
        });
    }
}
