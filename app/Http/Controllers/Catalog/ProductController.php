<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Uom;
use App\Models\TaxCategory;
use App\Models\AttributeSet;
use App\Models\Company;
use App\Models\Account;
use App\Services\Catalog\ProductAppService;
use App\Domain\Catalog\ProductTypeTemplates;
use App\Domain\Catalog\ProductRulesBundle;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * ProductController
 *
 * Unified controller for managing all product kinds.
 * Supports tabbed UI with sub-kinds grouped by main category.
 */
class ProductController extends Controller
{
    public function __construct(protected ProductAppService $productService)
    {
    }

    /**
     * Display listing of products filtered by group
     */
    public function index(Request $request, string $group = 'trade')
    {
        $kindGroups = ProductRulesBundle::getKindGroups();

        // Validate group
        if (!isset($kindGroups[$group])) {
            $group = 'trade';
        }

        $allowedKinds = $kindGroups[$group]['kinds'];

        $query = Product::with(['category', 'defaultUom', 'taxCategory', 'companies'])
            ->whereIn('kind', $allowedKinds);

        // Apply filters
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'ilike', "%{$search}%")
                  ->orWhere('name', 'ilike', "%{$search}%");
            });
        }

        if ($kind = $request->get('kind')) {
            if (in_array($kind, $allowedKinds, true)) {
                $query->where('kind', $kind);
            }
        }

        if ($categoryId = $request->get('product_category_id')) {
            $query->where('product_category_id', $categoryId);
        }

        if ($request->has('is_active') && $request->get('is_active') !== '') {
            $query->where('is_active', filter_var($request->get('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        // Sorting and pagination
        $perPage = (int) $request->get('per_page', 10);
        $sort = $request->get('sort', 'name');
        $order = $request->get('order', 'asc');
        $allowedSorts = ['name', 'code', 'kind', 'is_active', 'created_at'];

        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'name';
        }
        if (!in_array(strtolower($order), ['asc', 'desc'], true)) {
            $order = 'asc';
        }

        $products = $query->orderBy($sort, $order)->paginate($perPage)->withQueryString();

        // Build kind options for filter dropdown
        $kindOptions = [];
        foreach ($allowedKinds as $kind) {
            $kindOptions[] = [
                'value' => $kind,
                'label' => ProductRulesBundle::getKindLabel($kind),
            ];
        }

        return Inertia::render('Catalog/Products/Index', [
            'items' => $products,
            'filters' => $request->all(),
            'categories' => ProductCategory::orderBy('name')->get(),
            'perPage' => $perPage,
            'sort' => $sort,
            'order' => $order,
            'group' => $group,
            'groupLabel' => $kindGroups[$group]['label'],
            'kindGroups' => $kindGroups,
            'kindOptions' => $kindOptions,
        ]);
    }

    /**
     * Show template picker for product creation
     */
    public function create(Request $request, string $group = 'trade')
    {
        $kindGroups = ProductRulesBundle::getKindGroups();

        if (!isset($kindGroups[$group])) {
            $group = 'trade';
        }

        $templates = ProductTypeTemplates::getByGroup($group);

        return Inertia::render('Catalog/Products/Create', [
            'group' => $group,
            'groupLabel' => $kindGroups[$group]['label'],
            'kindGroups' => $kindGroups,
            'templates' => array_values($templates),
        ]);
    }

    /**
     * Show product creation form for specific template
     */
    public function createWithTemplate(Request $request, string $templateCode)
    {
        $template = ProductTypeTemplates::get($templateCode);

        if (!$template) {
            abort(404, 'Template not found');
        }

        $group = ProductRulesBundle::getGroupForKind($template['kind']);
        $attributeSet = AttributeSet::with('attributes')
            ->where('code', $template['attribute_set_code'])
            ->first();

        // Get categories that use this attribute set
        $categories = ProductCategory::query()
            ->when($attributeSet, fn($q) => $q->where('attribute_set_id', $attributeSet?->id))
            ->orderBy('name')
            ->get();

        return Inertia::render('Catalog/Products/Form', [
            'mode' => 'create',
            'product' => null,
            'template' => $template,
            'templateCode' => $templateCode,
            'group' => $group,
            'categories' => $categories,
            'uoms' => Uom::orderBy('name')->get(),
            'taxCategories' => TaxCategory::orderBy('name')->get(),
            'attributeSet' => $attributeSet,
            'companies' => Company::orderBy('name')->get(),
            'accounts' => Account::where('is_parent', false)->orderBy('code')->get(),
            'rulesBundle' => ProductRulesBundle::toArray(),
        ]);
    }

    /**
     * Store a new product
     */
    public function store(Request $request)
    {
        $templateCode = $request->input('template_code');
        $template = ProductTypeTemplates::get($templateCode);

        if (!$template) {
            abort(400, 'Invalid template');
        }

        $kind = $template['kind'];
        $data = $this->validateProduct($request, $kind, null);

        // Set defaults from template
        $data['kind'] = $kind;
        $data['cost_model'] = $data['cost_model'] ?? $template['cost_model'];

        $product = $this->productService->createProduct($data, $kind);

        $group = ProductRulesBundle::getGroupForKind($kind);

        return redirect()->route('catalog.products.edit', $product->id)
            ->with('success', 'Produk berhasil dibuat.');
    }

    /**
     * Show product edit form
     */
    public function edit(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $product->load(['category', 'defaultUom', 'taxCategory', 'companies', 'capabilities', 'variants', 'prepaidAccount']);

        $template = ProductTypeTemplates::getByKind($product->kind);
        $group = ProductRulesBundle::getGroupForKind($product->kind);

        $attributeSet = AttributeSet::with('attributes')
            ->where('code', $template['attribute_set_code'] ?? null)
            ->first();

        $categories = ProductCategory::query()
            ->when($attributeSet, fn($q) => $q->where('attribute_set_id', $attributeSet?->id))
            ->orderBy('name')
            ->get();

        return Inertia::render('Catalog/Products/Form', [
            'mode' => 'edit',
            'product' => $product,
            'template' => $template,
            'templateCode' => $template['template_code'] ?? null,
            'group' => $group,
            'categories' => $categories,
            'uoms' => Uom::orderBy('name')->get(),
            'taxCategories' => TaxCategory::orderBy('name')->get(),
            'attributeSet' => $attributeSet,
            'companies' => Company::orderBy('name')->get(),
            'accounts' => Account::where('is_parent', false)->orderBy('code')->get(),
            'rulesBundle' => ProductRulesBundle::toArray(),
        ]);
    }

    /**
     * Show product details
     */
    public function show(int $id)
    {
        $product = Product::findOrFail($id);
        $product->load(['category', 'defaultUom', 'taxCategory', 'companies', 'capabilities', 'variants', 'priceListItems.priceList']);

        $template = ProductTypeTemplates::getByKind($product->kind);
        $group = ProductRulesBundle::getGroupForKind($product->kind);

        return Inertia::render('Catalog/Products/Show', [
            'product' => $product,
            'template' => $template,
            'group' => $group,
            'kindLabel' => ProductRulesBundle::getKindLabel($product->kind),
        ]);
    }

    /**
     * Update an existing product
     */
    public function update(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $data = $this->validateProduct($request, $product->kind, $product->id);

        $this->productService->updateProduct($product, $data, $product->kind);

        return redirect()->route('catalog.products.edit', $product->id)
            ->with('success', 'Produk berhasil diubah.');
    }

    /**
     * Delete a product
     */
    public function destroy(int $id)
    {
        $product = Product::findOrFail($id);
        $group = ProductRulesBundle::getGroupForKind($product->kind);

        $this->productService->deleteProduct($product);

        return redirect()->route('catalog.products.index', ['group' => $group])
            ->with('success', 'Produk berhasil dihapus.');
    }

    /**
     * Validate product input based on kind
     */
    protected function validateProduct(Request $request, string $kind, ?int $productId): array
    {
        $uniqueRule = 'unique:products,code';
        if ($productId) {
            $uniqueRule .= ',' . $productId;
        }

        // Base validation rules
        $rules = [
            'code' => ['required', 'string', 'max:50', $uniqueRule],
            'name' => ['required', 'string', 'max:255'],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'attribute_set_id' => ['nullable', 'exists:attribute_sets,id'],
            'attributes' => ['array'],
            'default_uom_id' => ['nullable', 'exists:uoms,id'],
            'tax_category_id' => ['nullable', 'exists:tax_categories,id'],
            'revenue_account_id' => ['nullable', 'exists:accounts,id'],
            'cogs_account_id' => ['nullable', 'exists:accounts,id'],
            'inventory_account_id' => ['nullable', 'exists:accounts,id'],
            'prepaid_account_id' => ['nullable', 'exists:accounts,id'],
            'cost_model' => ['nullable', 'string', 'in:' . implode(',', ProductRulesBundle::COST_MODELS)],
            'is_active' => ['boolean'],
            'capabilities' => ['array'],
            'company_ids' => ['sometimes', 'array'],
            'company_ids.*' => ['exists:companies,id'],
        ];

        // Add kind-specific validation
        $requiredCapabilities = ProductRulesBundle::getRequiredCapabilities($kind);

        // If inventory_tracked is required, default_uom_id is required
        if (in_array('inventory_tracked', $requiredCapabilities, true)) {
            $rules['default_uom_id'] = ['required', 'exists:uoms,id'];
        }

        return $request->validate($rules);
    }
}
