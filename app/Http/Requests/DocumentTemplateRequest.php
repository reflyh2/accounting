<?php

namespace App\Http\Requests;

use App\Enums\Documents\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentTemplateRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'exists:companies,id'],
            'document_type' => ['required', Rule::enum(DocumentType::class)],
            'name' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'css_styles' => ['nullable', 'string'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'page_size' => ['nullable', 'string', 'in:A4,Letter,A5,Legal'],
            'page_orientation' => ['nullable', 'string', 'in:portrait,landscape'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'company_id' => 'perusahaan',
            'document_type' => 'tipe dokumen',
            'name' => 'nama template',
            'content' => 'konten template',
            'css_styles' => 'CSS styles',
            'is_default' => 'default',
            'is_active' => 'aktif',
            'page_size' => 'ukuran halaman',
            'page_orientation' => 'orientasi halaman',
        ];
    }
}
