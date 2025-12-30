<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\UserDiscountLimit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserDiscountLimitController extends Controller
{
    public function index(Request $request)
    {
        $query = UserDiscountLimit::with(['user', 'product', 'productCategory']);

        // Apply filters
        if ($request->filled('user_global_id')) {
            $userIds = is_array($request->user_global_id) ? $request->user_global_id : [$request->user_global_id];
            $query->whereIn('user_global_id', $userIds);
        }

        if ($request->filled('product_category_id')) {
            $categoryIds = is_array($request->product_category_id) ? $request->product_category_id : [$request->product_category_id];
            $query->whereIn('product_category_id', $categoryIds);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active === '1');
        }

        // Sorting
        $sortField = $request->get('sort', 'id');
        $sortOrder = $request->get('order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        $limits = $query->paginate($request->get('per_page', 15))->withQueryString();

        return Inertia::render('Catalog/UserDiscountLimits/Index', [
            'limits' => $limits,
            'filters' => $request->all(),
            'perPage' => $request->get('per_page', 15),
            'sort' => $sortField,
            'order' => $sortOrder,
            'users' => $this->userOptions(),
            'products' => $this->productOptions(),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Catalog/UserDiscountLimits/Create', [
            'filters' => $request->all(),
            'users' => $this->userOptions(),
            'products' => $this->productOptions(),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);
        $data['created_by'] = auth()->user->global_id;

        UserDiscountLimit::create($data);

        return redirect()->route('catalog.user-discount-limits.index')
            ->with('success', 'Batas diskon berhasil dibuat.');
    }

    public function edit(Request $request, UserDiscountLimit $userDiscountLimit)
    {
        $userDiscountLimit->load(['user', 'product', 'productCategory']);

        return Inertia::render('Catalog/UserDiscountLimits/Edit', [
            'limit' => $userDiscountLimit,
            'filters' => $request->all(),
            'users' => $this->userOptions(),
            'products' => $this->productOptions(),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(Request $request, UserDiscountLimit $userDiscountLimit)
    {
        $data = $this->validatePayload($request);
        $data['updated_by'] = auth()->user->global_id;

        $userDiscountLimit->update($data);

        return redirect()->route('catalog.user-discount-limits.index')
            ->with('success', 'Batas diskon berhasil diperbarui.');
    }

    public function destroy(UserDiscountLimit $userDiscountLimit)
    {
        $userDiscountLimit->delete();

        return redirect()->route('catalog.user-discount-limits.index')
            ->with('success', 'Batas diskon berhasil dihapus.');
    }

    private function validatePayload(Request $request): array
    {
        $data = $request->validate([
            'user_global_id' => ['required', 'exists:users,global_id'],
            'product_id' => ['nullable', 'exists:products,id'],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'max_discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        // Ensure only one of product or category is set (or neither for global)
        if (!empty($data['product_id']) && !empty($data['product_category_id'])) {
            abort(422, 'Tidak dapat mengatur produk dan kategori sekaligus. Pilih satu tipe cakupan.');
        }

        $data['is_active'] = $data['is_active'] ?? true;

        return $data;
    }

    private function userOptions()
    {
        return User::orderBy('name')
            ->get(['global_id', 'name', 'email']);
    }

    private function productOptions()
    {
        return Product::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    private function categoryOptions()
    {
        return ProductCategory::orderBy('name')
            ->get(['id', 'name']);
    }
}
