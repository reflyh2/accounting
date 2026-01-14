<?php

namespace App\Http\Controllers;

use App\Enums\Documents\DocumentType;
use App\Http\Requests\DocumentTemplateRequest;
use App\Models\Company;
use App\Models\DocumentTemplate;
use App\Services\DocumentTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class DocumentTemplateController extends Controller
{
    public function __construct(
        protected DocumentTemplateService $service
    ) {}

    /**
     * Display a listing of document templates.
     */
    public function index(Request $request): Response
    {
        $query = DocumentTemplate::with(['company', 'createdBy', 'updatedBy']);

        // Filter by company
        if ($request->filled('company_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('company_id', $request->company_id)
                    ->orWhereNull('company_id');
            });
        }

        // Filter by document type
        if ($request->filled('document_type')) {
            $query->forType($request->document_type);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search by name
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $templates = $query->orderBy('company_id')
            ->orderBy('document_type')
            ->orderByDesc('is_default')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('DocumentTemplates/Index', [
            'templates' => $templates,
            'filters' => $request->only(['company_id', 'document_type', 'is_active', 'search']),
            'companies' => Company::select('id', 'name')->orderBy('name')->get(),
            'documentTypes' => DocumentType::options(),
        ]);
    }

    /**
     * Show the form for creating a new template.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('DocumentTemplates/Create', [
            'companies' => Company::select('id', 'name')->orderBy('name')->get(),
            'documentTypes' => DocumentType::options(),
            'pageSizes' => $this->pageSizeOptions(),
            'pageOrientations' => $this->pageOrientationOptions(),
            'defaultDocumentType' => $request->get('document_type'),
            'defaultCompanyId' => $request->get('company_id'),
        ]);
    }

    /**
     * Store a newly created template.
     */
    public function store(DocumentTemplateRequest $request): RedirectResponse
    {
        $template = $this->service->create($request->validated());

        Session::flash('success', 'Template berhasil dibuat.');

        return Redirect::route('document-templates.show', $template);
    }

    /**
     * Display the specified template.
     */
    public function show(DocumentTemplate $documentTemplate): Response
    {
        $documentTemplate->load(['company', 'createdBy', 'updatedBy']);

        return Inertia::render('DocumentTemplates/Show', [
            'template' => $documentTemplate,
            'placeholders' => $this->service->getAvailablePlaceholders($documentTemplate->document_type->value),
        ]);
    }

    /**
     * Show the form for editing the template.
     */
    public function edit(DocumentTemplate $documentTemplate): Response
    {
        $documentTemplate->load(['company']);

        return Inertia::render('DocumentTemplates/Edit', [
            'template' => $documentTemplate,
            'companies' => Company::select('id', 'name')->orderBy('name')->get(),
            'documentTypes' => DocumentType::options(),
            'pageSizes' => $this->pageSizeOptions(),
            'pageOrientations' => $this->pageOrientationOptions(),
            'placeholders' => $this->service->getAvailablePlaceholders($documentTemplate->document_type->value),
        ]);
    }

    /**
     * Update the specified template.
     */
    public function update(DocumentTemplateRequest $request, DocumentTemplate $documentTemplate): RedirectResponse
    {
        $this->service->update($documentTemplate, $request->validated());

        Session::flash('success', 'Template berhasil diperbarui.');

        return Redirect::route('document-templates.show', $documentTemplate);
    }

    /**
     * Remove the specified template.
     */
    public function destroy(DocumentTemplate $documentTemplate): RedirectResponse
    {
        if ($documentTemplate->is_default) {
            Session::flash('error', 'Tidak dapat menghapus template default.');
            return Redirect::back();
        }

        $this->service->delete($documentTemplate);

        Session::flash('success', 'Template berhasil dihapus.');

        return Redirect::route('document-templates.index');
    }

    /**
     * Set a template as default.
     */
    public function setDefault(DocumentTemplate $documentTemplate): RedirectResponse
    {
        $this->service->setAsDefault($documentTemplate);

        Session::flash('success', 'Template berhasil dijadikan default.');

        return Redirect::back();
    }

    /**
     * Preview template with sample data.
     */
    public function preview(DocumentTemplate $documentTemplate): Response
    {
        // Generate sample preview content
        $sampleData = $this->generateSampleData($documentTemplate->document_type->value);
        $previewHtml = $this->renderPreview($documentTemplate, $sampleData);

        return Inertia::render('DocumentTemplates/Preview', [
            'template' => $documentTemplate,
            'previewHtml' => $previewHtml,
        ]);
    }

    /**
     * Duplicate an existing template.
     */
    public function duplicate(Request $request, DocumentTemplate $documentTemplate): RedirectResponse
    {
        $targetCompanyId = $request->input('company_id', $documentTemplate->company_id);

        $newTemplate = $this->service->duplicate($documentTemplate, $targetCompanyId);

        Session::flash('success', 'Template berhasil diduplikasi.');

        return Redirect::route('document-templates.edit', $newTemplate);
    }

    /**
     * Get available placeholders for a document type (API endpoint).
     */
    public function placeholders(Request $request)
    {
        $documentType = $request->get('document_type', 'sales_order');
        
        return response()->json(
            $this->service->getAvailablePlaceholders($documentType)
        );
    }

    /**
     * Page size options.
     */
    protected function pageSizeOptions(): array
    {
        return [
            ['value' => 'A4', 'label' => 'A4'],
            ['value' => 'Letter', 'label' => 'Letter'],
            ['value' => 'A5', 'label' => 'A5'],
            ['value' => 'Legal', 'label' => 'Legal'],
        ];
    }

    /**
     * Page orientation options.
     */
    protected function pageOrientationOptions(): array
    {
        return [
            ['value' => 'portrait', 'label' => 'Portrait'],
            ['value' => 'landscape', 'label' => 'Landscape'],
        ];
    }

    /**
     * Generate sample data for preview.
     */
    protected function generateSampleData(string $documentType): array
    {
        $common = [
            'company.name' => 'PT Contoh Perusahaan',
            'company.address' => 'Jl. Contoh No. 123',
            'company.city' => 'Jakarta',
            'company.phone' => '021-1234567',
            'company.email' => 'info@contoh.com',
            'branch.name' => 'Cabang Utama',
            'branch.address' => 'Jl. Cabang No. 1',
            'partner.name' => 'Customer ABC',
            'partner.code' => 'CUST-001',
            'partner.address' => 'Jl. Customer No. 456',
            'partner.phone' => '022-9876543',
            'currency.code' => 'IDR',
            'created_by.name' => 'Admin User',
            'notes' => 'Catatan contoh untuk preview template.',
        ];

        $lines = [
            [
                'index' => 1,
                'product_name' => 'Produk A',
                'variant_sku' => 'SKU-A001',
                'description' => 'Deskripsi Produk A',
                'quantity' => '10',
                'uom_code' => 'PCS',
                'unit_price' => '100.000',
                'discount_rate' => '5',
                'tax_rate' => '11',
                'tax_amount' => '104.500',
                'line_total' => '1.054.500',
            ],
            [
                'index' => 2,
                'product_name' => 'Produk B',
                'variant_sku' => 'SKU-B002',
                'description' => 'Deskripsi Produk B',
                'quantity' => '5',
                'uom_code' => 'PCS',
                'unit_price' => '200.000',
                'discount_rate' => '0',
                'tax_rate' => '11',
                'tax_amount' => '110.000',
                'line_total' => '1.110.000',
            ],
        ];

        $specific = match ($documentType) {
            'sales_order' => [
                'document_number' => 'SO/2026/01/0001',
                'document_date' => '14 Januari 2026',
                'expected_delivery_date' => '21 Januari 2026',
                'quote_valid_until' => '28 Januari 2026',
                'payment_terms' => 'Net 30',
                'customer_reference' => 'PO-12345',
                'subtotal' => '1.950.000',
                'tax_total' => '214.500',
                'total_amount' => '2.164.500',
            ],
            'sales_delivery' => [
                'document_number' => 'DO/2026/01/0001',
                'document_date' => '15 Januari 2026',
                'total_quantity' => '15',
                'so_numbers' => 'SO/2026/01/0001',
            ],
            'sales_invoice' => [
                'document_number' => 'INV/2026/01/0001',
                'document_date' => '16 Januari 2026',
                'due_date' => '15 Februari 2026',
                'status' => 'Posted',
                'subtotal' => '1.950.000',
                'tax_total' => '214.500',
                'total_amount' => '2.164.500',
                'so_numbers' => 'SO/2026/01/0001',
            ],
            default => [],
        };

        return array_merge($common, $specific, ['lines' => $lines]);
    }

    /**
     * Render preview with sample data.
     */
    protected function renderPreview(DocumentTemplate $template, array $sampleData): string
    {
        $content = $template->content;

        // Process loops
        $pattern = '/\{\{#(\w+)\}\}(.*?)\{\{\/\1\}\}/s';
        $content = preg_replace_callback($pattern, function ($matches) use ($sampleData) {
            $arrayName = $matches[1];
            $template = $matches[2];

            if (!isset($sampleData[$arrayName]) || !is_array($sampleData[$arrayName])) {
                return '';
            }

            $output = '';
            foreach ($sampleData[$arrayName] as $item) {
                $row = $template;
                foreach ($item as $key => $value) {
                    $row = str_replace('{{' . $key . '}}', $value ?? '', $row);
                }
                $output .= $row;
            }
            return $output;
        }, $content);

        // Replace simple placeholders
        foreach ($sampleData as $key => $value) {
            if (!is_array($value)) {
                $content = str_replace('{{' . $key . '}}', $value ?? '', $content);
            }
        }

        // Add CSS
        if ($template->css_styles) {
            $content = '<style>' . $template->css_styles . '</style>' . $content;
        }

        return $content;
    }
}
