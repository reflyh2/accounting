<?php

namespace App\Services;

use App\Enums\Documents\DocumentType;
use App\Models\DocumentTemplate;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Partner;
use App\Models\SalesOrder;
use App\Models\SalesDelivery;
use App\Models\SalesInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DocumentTemplateService
{
    /**
     * Create a new document template.
     */
    public function create(array $data): DocumentTemplate
    {
        return DB::transaction(function () use ($data) {
            $data['created_by'] = Auth::id();
            $data['updated_by'] = Auth::id();

            // If setting as default, unset other defaults for same company+type
            if (!empty($data['is_default'])) {
                $this->unsetOtherDefaults($data['company_id'] ?? null, $data['document_type']);
            }

            return DocumentTemplate::create($data);
        });
    }

    /**
     * Update an existing document template.
     */
    public function update(DocumentTemplate $template, array $data): DocumentTemplate
    {
        return DB::transaction(function () use ($template, $data) {
            $data['updated_by'] = Auth::id();

            // If setting as default, unset other defaults for same company+type
            if (!empty($data['is_default']) && !$template->is_default) {
                $this->unsetOtherDefaults($template->company_id, $template->document_type->value);
            }

            $template->update($data);
            return $template->fresh();
        });
    }

    /**
     * Delete a document template.
     */
    public function delete(DocumentTemplate $template): bool
    {
        return $template->delete();
    }

    /**
     * Set a template as the default for its company and type.
     */
    public function setAsDefault(DocumentTemplate $template): void
    {
        DB::transaction(function () use ($template) {
            // Unset other defaults
            $this->unsetOtherDefaults($template->company_id, $template->document_type->value);

            // Set this one as default
            $template->update([
                'is_default' => true,
                'updated_by' => Auth::id(),
            ]);
        });
    }

    /**
     * Duplicate an existing template.
     */
    public function duplicate(DocumentTemplate $template, ?int $targetCompanyId = null): DocumentTemplate
    {
        $newData = $template->toArray();
        unset($newData['id'], $newData['created_at'], $newData['updated_at']);

        $newData['name'] = $template->name . ' (Copy)';
        $newData['is_default'] = false;
        $newData['company_id'] = $targetCompanyId ?? $template->company_id;
        $newData['created_by'] = Auth::id();
        $newData['updated_by'] = Auth::id();

        return DocumentTemplate::create($newData);
    }

    /**
     * Render a template with document data.
     */
    public function renderTemplate(DocumentTemplate $template, Model $document): string
    {
        $placeholders = $this->extractPlaceholders($document);
        $content = $template->content;

        // Handle loop sections (e.g., {{#lines}}...{{/lines}})
        $content = $this->processLoops($content, $placeholders);

        // Replace simple placeholders
        foreach ($placeholders as $key => $value) {
            if (!is_array($value)) {
                $content = str_replace('{{' . $key . '}}', $value ?? '', $content);
            }
        }

        // Add CSS styles with print background color fix
        $printCss = '* { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }';
        if ($template->css_styles) {
            $content = '<style>' . $printCss . ' ' . $template->css_styles . '</style>' . $content;
        } else {
            $content = '<style>' . $printCss . '</style>' . $content;
        }

        return $content;
    }

    /**
     * Extract placeholder values from a document model.
     */
    protected function extractPlaceholders(Model $document): array
    {
        $placeholders = [];

        // Document fields
        if ($document instanceof SalesOrder) {
            $placeholders = array_merge($placeholders, $this->extractSalesOrderPlaceholders($document));
        } elseif ($document instanceof SalesDelivery) {
            $placeholders = array_merge($placeholders, $this->extractSalesDeliveryPlaceholders($document));
        } elseif ($document instanceof SalesInvoice) {
            $placeholders = array_merge($placeholders, $this->extractSalesInvoicePlaceholders($document));
        }

        // Company fields
        if ($company = $this->getCompany($document)) {
            foreach ($company->toArray() as $key => $value) {
                if (!is_array($value) && !is_object($value)) {
                    $placeholders['company.' . $key] = $value;
                }
            }
            // Add logo as base64 data URI for reliable rendering in print documents
            $placeholders['company.logo_url'] = $this->getLogoDataUri($company);
        }

        // Branch fields
        if ($branch = $document->branch) {
            foreach ($branch->toArray() as $key => $value) {
                if (!is_array($value) && !is_object($value)) {
                    $placeholders['branch.' . $key] = $value;
                }
            }
        }

        // Partner fields
        if ($partner = $document->partner) {
            foreach ($partner->toArray() as $key => $value) {
                if (!is_array($value) && !is_object($value)) {
                    $placeholders['partner.' . $key] = $value;
                }
            }
        }

        // Currency fields
        if ($currency = $document->currency) {
            foreach ($currency->toArray() as $key => $value) {
                if (!is_array($value) && !is_object($value)) {
                    $placeholders['currency.' . $key] = $value;
                }
            }
        }

        // Creator
        if ($creator = $document->creator) {
            $placeholders['created_by.name'] = $creator->name;
            $placeholders['created_by.email'] = $creator->email;
        }

        // Notes
        $placeholders['notes'] = $document->notes ?? '';

        return $placeholders;
    }

    /**
     * Extract Sales Order specific placeholders.
     */
    protected function extractSalesOrderPlaceholders(SalesOrder $order): array
    {
        return [
            'document_number' => $order->order_number,
            'document_date' => $this->formatDate($order->order_date),
            'document_date_long' => $this->formatDateLong($order->order_date),
            'document_date_short' => $this->formatDateShort($order->order_date),
            'expected_delivery_date' => $this->formatDate($order->expected_delivery_date),
            'quote_valid_until' => $this->formatDate($order->quote_valid_until),
            'payment_terms' => $order->payment_terms,
            'customer_reference' => $order->customer_reference,
            'subtotal' => $this->formatNumber($order->subtotal),
            'tax_total' => $this->formatNumber($order->tax_total),
            'total_amount' => $this->formatNumber($order->total_amount),
            'total_terbilang_id' => $this->terbilang($order->total_amount),
            'total_terbilang_en' => $this->spellOutEnglish($order->total_amount),
            'lines' => $this->extractOrderLines($order),
        ];
    }

    /**
     * Extract Sales Delivery specific placeholders.
     */
    protected function extractSalesDeliveryPlaceholders(SalesDelivery $delivery): array
    {
        return [
            'document_number' => $delivery->delivery_number,
            'document_date' => $this->formatDate($delivery->delivery_date),
            'document_date_long' => $this->formatDateLong($delivery->delivery_date),
            'document_date_short' => $this->formatDateShort($delivery->delivery_date),
            'total_quantity' => $this->formatNumber($delivery->total_quantity),
            'so_numbers' => $delivery->salesOrders?->pluck('order_number')->implode(', '),
            'lines' => $this->extractDeliveryLines($delivery),
        ];
    }

    /**
     * Extract Sales Invoice specific placeholders.
     */
    protected function extractSalesInvoicePlaceholders(SalesInvoice $invoice): array
    {
        return [
            'document_number' => $invoice->invoice_number,
            'document_date' => $this->formatDate($invoice->invoice_date),
            'document_date_long' => $this->formatDateLong($invoice->invoice_date),
            'document_date_short' => $this->formatDateShort($invoice->invoice_date),
            'due_date' => $this->formatDate($invoice->due_date),
            'due_date_long' => $this->formatDateLong($invoice->due_date),
            'due_date_short' => $this->formatDateShort($invoice->due_date),
            'status' => $invoice->status,
            'subtotal' => $this->formatNumber($invoice->subtotal),
            'tax_total' => $this->formatNumber($invoice->tax_total),
            'total_amount' => $this->formatNumber($invoice->total_amount),
            'total_terbilang_id' => $this->terbilang($invoice->total_amount),
            'total_terbilang_en' => $this->spellOutEnglish($invoice->total_amount),
            'so_numbers' => $invoice->salesOrders?->pluck('order_number')->implode(', '),
            'lines' => $this->extractInvoiceLines($invoice),
        ];
    }

    /**
     * Extract order line items.
     */
    protected function extractOrderLines(SalesOrder $order): array
    {
        $lines = [];
        foreach ($order->lines as $index => $line) {
            $lines[] = [
                'index' => $index + 1,
                'product_name' => $line->variant?->product?->name ?? $line->description,
                'variant_sku' => $line->variant?->sku ?? '',
                'description' => $line->description ?? '',
                'quantity' => $this->formatNumber($line->quantity),
                'uom_code' => $line->uom?->code ?? '',
                'unit_price' => $this->formatNumber($line->unit_price),
                'discount_rate' => $this->formatNumber($line->discount_rate ?? 0),
                'tax_rate' => $this->formatNumber($line->tax_rate ?? 0),
                'tax_amount' => $this->formatNumber($line->tax_amount ?? 0),
                'line_total' => $this->formatNumber($line->line_total),
            ];
        }
        return $lines;
    }

    /**
     * Extract delivery line items.
     */
    protected function extractDeliveryLines(SalesDelivery $delivery): array
    {
        $lines = [];
        foreach ($delivery->lines as $index => $line) {
            $lines[] = [
                'index' => $index + 1,
                'product_name' => $line->variant?->product?->name ?? $line->description,
                'variant_sku' => $line->variant?->sku ?? '',
                'description' => $line->description ?? '',
                'quantity' => $this->formatNumber($line->quantity),
                'uom_code' => $line->uom?->code ?? '',
            ];
        }
        return $lines;
    }

    /**
     * Extract invoice line items.
     */
    protected function extractInvoiceLines(SalesInvoice $invoice): array
    {
        $lines = [];
        foreach ($invoice->lines as $index => $line) {
            $lines[] = [
                'index' => $index + 1,
                'product_name' => $line->variant?->product?->name ?? $line->description,
                'variant_sku' => $line->variant?->sku ?? '',
                'description' => $line->description ?? '',
                'quantity' => $this->formatNumber($line->quantity),
                'uom_code' => $line->uom_label ?? '',
                'unit_price' => $this->formatNumber($line->unit_price),
                'discount_rate' => $this->formatNumber($line->discount_rate ?? 0),
                'tax_rate' => $this->formatNumber($line->tax_rate ?? 0),
                'line_total' => $this->formatNumber($line->line_total),
            ];
        }
        return $lines;
    }

    /**
     * Process loop sections in template.
     */
    protected function processLoops(string $content, array $placeholders): string
    {
        // Match {{#arrayName}}...{{/arrayName}}
        $pattern = '/\{\{#(\w+)\}\}(.*?)\{\{\/\1\}\}/s';

        return preg_replace_callback($pattern, function ($matches) use ($placeholders) {
            $arrayName = $matches[1];
            $template = $matches[2];

            if (!isset($placeholders[$arrayName]) || !is_array($placeholders[$arrayName])) {
                return '';
            }

            $output = '';
            foreach ($placeholders[$arrayName] as $item) {
                $row = $template;
                foreach ($item as $key => $value) {
                    $row = str_replace('{{' . $key . '}}', $value ?? '', $row);
                }
                $output .= $row;
            }
            return $output;
        }, $content);
    }

    /**
     * Get available placeholders for a document type.
     */
    public function getAvailablePlaceholders(string $documentType): array
    {
        // Curated list of company fields that make sense for documents
        $companyFields = [
            'logo_url' => 'Logo (URL/Base64)',
            'name' => 'Nama Perusahaan',
            'legal_name' => 'Nama Legal',
            'tax_id' => 'NPWP',
            'address' => 'Alamat',
            'city' => 'Kota',
            'province' => 'Provinsi',
            'postal_code' => 'Kode Pos',
            'phone' => 'Telepon',
            'email' => 'Email',
            'website' => 'Website',
        ];
        
        // Curated list of branch fields
        $branchFields = [
            'name' => 'Nama Cabang',
            'address' => 'Alamat Cabang',
        ];
        
        // Curated list of partner fields that make sense for documents
        $partnerFields = [
            'name' => 'Nama',
            'code' => 'Kode',
            'phone' => 'Telepon',
            'email' => 'Email',
            'address' => 'Alamat',
            'city' => 'Kota',
            'region' => 'Provinsi/Wilayah',
            'postal_code' => 'Kode Pos',
            'country' => 'Negara',
            'tax_id' => 'NPWP',
            'registration_number' => 'No. Registrasi',
        ];
        
        $common = [
            'company' => $companyFields,
            'branch' => $branchFields,
            'partner' => $partnerFields,
            'currency' => ['code' => 'Kode', 'name' => 'Nama', 'symbol' => 'Simbol'],
            'created_by' => ['name' => 'Nama', 'email' => 'Email'],
            'notes' => 'Catatan Dokumen',
        ];

        $specific = match ($documentType) {
            'sales_order' => [
                'document_number' => 'Nomor Dokumen',
                'document_date' => 'Tanggal (Default)',
                'document_date_long' => 'Tanggal (06 October 2025)',
                'document_date_short' => 'Tanggal (dd/mm/yyyy)',
                'expected_delivery_date' => 'Tanggal Kirim',
                'quote_valid_until' => 'Berlaku Sampai',
                'payment_terms' => 'Syarat Pembayaran',
                'customer_reference' => 'Referensi Customer',
                'subtotal' => 'Subtotal',
                'tax_total' => 'Total Pajak',
                'total_amount' => 'Grand Total',
                'total_terbilang_id' => 'Terbilang (ID)',
                'total_terbilang_en' => 'Terbilang (EN)',
            ],
            'sales_delivery' => [
                'document_number' => 'Nomor Surat Jalan',
                'document_date' => 'Tanggal (Default)',
                'document_date_long' => 'Tanggal (06 October 2025)',
                'document_date_short' => 'Tanggal (dd/mm/yyyy)',
                'total_quantity' => 'Total Qty',
                'so_numbers' => 'Nomor SO',
            ],
            'sales_invoice' => [
                'document_number' => 'Nomor Faktur',
                'document_date' => 'Tanggal (Default)',
                'document_date_long' => 'Tanggal (06 October 2025)',
                'document_date_short' => 'Tanggal (dd/mm/yyyy)',
                'due_date' => 'Jatuh Tempo',
                'due_date_long' => 'Jatuh Tempo (Long)',
                'due_date_short' => 'Jatuh Tempo (Short)',
                'status' => 'Status',
                'subtotal' => 'Subtotal',
                'tax_total' => 'Total Pajak',
                'total_amount' => 'Grand Total',
                'total_terbilang_id' => 'Terbilang (ID)',
                'total_terbilang_en' => 'Terbilang (EN)',
                'so_numbers' => 'Nomor SO',
            ],
            default => [],
        };

        $lineItems = [
            'index' => 'Nomor Baris',
            'product_name' => 'Nama Produk',
            'variant_sku' => 'SKU Varian',
            'description' => 'Deskripsi',
            'quantity' => 'Qty',
            'uom_code' => 'Satuan',
            'unit_price' => 'Harga Satuan',
            'discount_rate' => 'Diskon (%)',
            'tax_rate' => 'Pajak (%)',
            'line_total' => 'Total',
        ];

        return [
            'common' => $common,
            'document' => $specific,
            'lines' => $lineItems,
        ];
    }

    /**
     * Get fillable fields from a model class.
     */
    protected function getModelFields(string $modelClass): array
    {
        $model = new $modelClass;
        return $model->getFillable();
    }

    /**
     * Get company from document.
     */
    protected function getCompany(Model $document): ?Company
    {
        if (method_exists($document, 'company') && $document->company) {
            return $document->company;
        }
        if ($document->branch?->branchGroup?->company) {
            return $document->branch->branchGroup->company;
        }
        return null;
    }

    /**
     * Format date for display.
     */
    protected function formatDate($date): string
    {
        if (!$date) {
            return '—';
        }
        return \Carbon\Carbon::parse($date)->translatedFormat('d F Y');
    }

    /**
     * Format number for display.
     */
    protected function formatNumber($number, int $decimals = 0): string
    {
        if ($number === null) {
            return '0';
        }
        return number_format((float) $number, $decimals, ',', '.');
    }

    /**
     * Unset other default templates for same company and type.
     */
    protected function unsetOtherDefaults(?int $companyId, string $documentType): void
    {
        DocumentTemplate::where('company_id', $companyId)
            ->where('document_type', $documentType)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    /**
     * Get company logo as base64 data URI for embedding in documents.
     */
    protected function getLogoDataUri(?Company $company): string
    {
        if (!$company || !$company->logo_path) {
            return '';
        }

        try {
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            
            if (!$disk->exists($company->logo_path)) {
                return '';
            }

            $contents = $disk->get($company->logo_path);
            $extension = pathinfo($company->logo_path, PATHINFO_EXTENSION);
            $mimeTypes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif'];
            $mimeType = $mimeTypes[strtolower($extension)] ?? 'image/png';
            
            return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Format date in long format (e.g., 06 October 2025).
     */
    protected function formatDateLong($date): string
    {
        if (!$date) {
            return '—';
        }
        return \Carbon\Carbon::parse($date)->translatedFormat('d F Y');
    }

    /**
     * Format date in short format (dd/mm/yyyy).
     */
    protected function formatDateShort($date): string
    {
        if (!$date) {
            return '—';
        }
        return \Carbon\Carbon::parse($date)->format('d/m/Y');
    }

    /**
     * Convert number to words in Bahasa Indonesia (terbilang).
     */
    protected function terbilang($number): string
    {
        if ($number === null || $number == 0) {
            return 'Nol Rupiah';
        }

        $number = abs((float) $number);
        $words = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
        
        if ($number < 12) {
            return $words[(int) $number] . ' Rupiah';
        } elseif ($number < 20) {
            return $words[(int) $number - 10] . ' Belas Rupiah';
        } elseif ($number < 100) {
            return $words[(int) ($number / 10)] . ' Puluh ' . $this->terbilangHelper($number % 10);
        } elseif ($number < 200) {
            return 'Seratus ' . $this->terbilangHelper($number - 100);
        } elseif ($number < 1000) {
            return $words[(int) ($number / 100)] . ' Ratus ' . $this->terbilangHelper($number % 100);
        } elseif ($number < 2000) {
            return 'Seribu ' . $this->terbilangHelper($number - 1000);
        } elseif ($number < 1000000) {
            return $this->terbilangHelper($number / 1000) . 'Ribu ' . $this->terbilangHelper($number % 1000);
        } elseif ($number < 1000000000) {
            return $this->terbilangHelper($number / 1000000) . 'Juta ' . $this->terbilangHelper($number % 1000000);
        } elseif ($number < 1000000000000) {
            return $this->terbilangHelper($number / 1000000000) . 'Miliar ' . $this->terbilangHelper($number % 1000000000);
        } elseif ($number < 1000000000000000) {
            return $this->terbilangHelper($number / 1000000000000) . 'Triliun ' . $this->terbilangHelper($number % 1000000000000);
        }

        return 'Jumlah terlalu besar';
    }

    /**
     * Helper for terbilang recursion (without "Rupiah" suffix).
     */
    private function terbilangHelper($number): string
    {
        if ($number == 0) {
            return '';
        }
        
        $words = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
        
        if ($number < 12) {
            return $words[(int) $number] . ' ';
        } elseif ($number < 20) {
            return $words[(int) $number - 10] . ' Belas ';
        } elseif ($number < 100) {
            return $words[(int) ($number / 10)] . ' Puluh ' . $this->terbilangHelper($number % 10);
        } elseif ($number < 200) {
            return 'Seratus ' . $this->terbilangHelper($number - 100);
        } elseif ($number < 1000) {
            return $words[(int) ($number / 100)] . ' Ratus ' . $this->terbilangHelper($number % 100);
        } elseif ($number < 2000) {
            return 'Seribu ' . $this->terbilangHelper($number - 1000);
        } elseif ($number < 1000000) {
            return $this->terbilangHelper($number / 1000) . 'Ribu ' . $this->terbilangHelper($number % 1000);
        } elseif ($number < 1000000000) {
            return $this->terbilangHelper($number / 1000000) . 'Juta ' . $this->terbilangHelper($number % 1000000);
        } elseif ($number < 1000000000000) {
            return $this->terbilangHelper($number / 1000000000) . 'Miliar ' . $this->terbilangHelper($number % 1000000000);
        }
        
        return $this->terbilangHelper($number / 1000000000000) . 'Triliun ' . $this->terbilangHelper($number % 1000000000000);
    }

    /**
     * Convert number to words in English.
     */
    protected function spellOutEnglish($number): string
    {
        if ($number === null || $number == 0) {
            return 'Zero Rupiah';
        }

        $number = abs((float) $number);
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        
        $result = $this->spellOutEnglishHelper($number, $ones, $tens);
        return trim($result) . ' Rupiah';
    }

    /**
     * Helper for English number spelling.
     */
    private function spellOutEnglishHelper($number, array $ones, array $tens): string
    {
        if ($number == 0) {
            return '';
        }
        
        if ($number < 20) {
            return $ones[(int) $number] . ' ';
        } elseif ($number < 100) {
            return $tens[(int) ($number / 10)] . ' ' . $this->spellOutEnglishHelper($number % 10, $ones, $tens);
        } elseif ($number < 1000) {
            return $ones[(int) ($number / 100)] . ' Hundred ' . $this->spellOutEnglishHelper($number % 100, $ones, $tens);
        } elseif ($number < 1000000) {
            return $this->spellOutEnglishHelper($number / 1000, $ones, $tens) . 'Thousand ' . $this->spellOutEnglishHelper($number % 1000, $ones, $tens);
        } elseif ($number < 1000000000) {
            return $this->spellOutEnglishHelper($number / 1000000, $ones, $tens) . 'Million ' . $this->spellOutEnglishHelper($number % 1000000, $ones, $tens);
        } elseif ($number < 1000000000000) {
            return $this->spellOutEnglishHelper($number / 1000000000, $ones, $tens) . 'Billion ' . $this->spellOutEnglishHelper($number % 1000000000, $ones, $tens);
        }
        
        return $this->spellOutEnglishHelper($number / 1000000000000, $ones, $tens) . 'Trillion ' . $this->spellOutEnglishHelper($number % 1000000000000, $ones, $tens);
    }
}

