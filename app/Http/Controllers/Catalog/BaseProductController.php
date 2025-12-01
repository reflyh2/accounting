<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Uom;
use App\Models\TaxCategory;
use App\Services\Catalog\ProductAppService;
use App\Domain\Catalog\ProductTypeTemplates;
use App\Models\AttributeSet;
use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;

abstract class BaseProductController extends Controller
{
    protected string $type;

    public function __construct(protected ProductAppService $productService)
    {
    }

    public function index(Request $request)
    {
        $query = Product::with(['category', 'defaultUom', 'taxCategory', 'companies'])
            ->where('kind', $this->type);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'ilike', "%{$search}%")
                  ->orWhere('name', 'ilike', "%{$search}%");
            });
        }

        if ($categoryId = $request->get('product_category_id')) {
            $query->where('product_category_id', $categoryId);
        }

        if ($request->has('is_active') && $request->get('is_active') !== '') {
            $query->where('is_active', filter_var($request->get('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = (int) $request->get('per_page', 10);
        $sort = $request->get('sort', 'name');
        $order = $request->get('order', 'asc');
        $allowedSorts = ['name','code','is_active','created_at'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'name';
        }
        if (!in_array(strtolower($order), ['asc','desc'], true)) {
            $order = 'asc';
        }
        $products = $query->orderBy($sort, $order)->paginate($perPage)->withQueryString();

        return Inertia::render($this->viewBase().'/Index', [
            'items' => $products,
            'filters' => $request->all(),
            'categories' => ProductCategory::orderBy('name')->get(),
            'perPage' => $perPage,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    public function create()
    {
        $template = ProductTypeTemplates::all()[$this->type] ?? [];
        $allowedSetCodes = $template['attribute_sets'] ?? [];
        $attributeSets = AttributeSet::with('attributes')->whereIn('code', $allowedSetCodes)->orderBy('name')->get();
        $categories = ProductCategory::query()
            ->whereIn('attribute_set_id', $attributeSets->pluck('id'))
            ->orderBy('name')->get();

        return Inertia::render($this->viewBase().'/Form', [
            'mode' => 'create',
            'product' => null,
            'categories' => $categories,
            'uoms' => Uom::orderBy('name')->get(),
            'taxCategories' => TaxCategory::orderBy('name')->get(),
            'attributeSets' => $attributeSets,
            'typeTemplate' => $template,
            'companies' => Company::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateBase($request);
        $product = $this->productService->createProduct($data, $this->type);
        return redirect()->route($this->routeBase().'.edit', $product->id)
            ->with('success', 'Produk berhasil dibuat.');
    }

    public function edit($id)
    {
        $product = Product::findOrFail($id);
        abort_unless($product->kind === $this->type, 404);
        $product->load(['category', 'defaultUom', 'taxCategory', 'companies']);
        $template = ProductTypeTemplates::all()[$this->type] ?? [];
        $allowedSetCodes = $template['attribute_sets'] ?? [];
        $attributeSets = AttributeSet::with('attributes')->whereIn('code', $allowedSetCodes)->orderBy('name')->get();
        $categories = ProductCategory::query()
            ->whereIn('attribute_set_id', $attributeSets->pluck('id'))
            ->orderBy('name')->get();
        return Inertia::render($this->viewBase().'/Form', [
            'mode' => 'edit',
            'product' => $product,
            'categories' => $categories,
            'uoms' => Uom::orderBy('name')->get(),
            'taxCategories' => TaxCategory::orderBy('name')->get(),
            'attributeSets' => $attributeSets,
            'typeTemplate' => $template,
            'companies' => Company::orderBy('name')->get(),
        ]);
    }

    public function show($id)
    {
        $product = Product::findOrFail($id);
        abort_unless($product->kind === $this->type, 404);
        $product->load(['category', 'defaultUom', 'taxCategory', 'companies', 'variants']);
        return Inertia::render($this->viewBase().'/Show', [
            'product' => $product,
        ]);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        abort_unless($product->kind === $this->type, 404);
        $data = $this->validateBase($request, $product->id);
        $this->productService->updateProduct($product, $data, $this->type);
        return redirect()->route($this->routeBase().'.edit', $product->id)
            ->with('success', 'Produk berhasil diubah.');
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        abort_unless($product->kind === $this->type, 404);
        $this->productService->deleteProduct($product);
        return redirect()->route($this->routeBase().'.index')
            ->with('success', 'Produk berhasil dihapus.');
    }

    protected function validateBase(Request $request, ?int $productId = null): array
    {
        $uniqueRule = 'unique:products,code';
        if ($productId) {
            $uniqueRule .= ',' . $productId;
        }
        return $request->validate([
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
            'is_active' => ['boolean'],
            'capabilities' => ['array'],
            'company_ids' => ['sometimes', 'array'],
            'company_ids.*' => ['exists:companies,id'],
        ]);
    }

    abstract protected function viewBase(): string;
    abstract protected function routeBase(): string;
}


