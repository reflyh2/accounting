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
        $limits = UserDiscountLimit::with(['user', 'product', 'productCategory'])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Catalog/UserDiscountLimits/Index', [
            'limits' => $limits,
            'filters' => $request->all(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Catalog/UserDiscountLimits/Form', [
            'mode' => 'create',
            'limit' => null,
            'users' => $this->userOptions(),
            'products' => $this->productOptions(),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);
        $data['created_by'] = auth()->user()->global_id;

        UserDiscountLimit::create($data);

        return redirect()->route('catalog.user-discount-limits.index')
            ->with('success', 'Discount limit created.');
    }

    public function edit(UserDiscountLimit $userDiscountLimit)
    {
        $userDiscountLimit->load(['user', 'product', 'productCategory']);

        return Inertia::render('Catalog/UserDiscountLimits/Form', [
            'mode' => 'edit',
            'limit' => $userDiscountLimit,
            'users' => $this->userOptions(),
            'products' => $this->productOptions(),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(Request $request, UserDiscountLimit $userDiscountLimit)
    {
        $data = $this->validatePayload($request);
        $data['updated_by'] = auth()->user()->global_id;

        $userDiscountLimit->update($data);

        return redirect()->route('catalog.user-discount-limits.index')
            ->with('success', 'Discount limit updated.');
    }

    public function destroy(UserDiscountLimit $userDiscountLimit)
    {
        $userDiscountLimit->delete();

        return redirect()->route('catalog.user-discount-limits.index')
            ->with('success', 'Discount limit deleted.');
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
            abort(422, 'Cannot set both product and category. Choose one scope type.');
        }

        $data['is_active'] = $data['is_active'] ?? true;

        return $data;
    }

    private function userOptions()
    {
        return User::orderBy('name')
            ->get(['global_id', 'name', 'email'])
            ->map(fn ($user) => [
                'value' => $user->global_id,
                'label' => $user->name,
                'email' => $user->email,
            ]);
    }

    private function productOptions()
    {
        return Product::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn ($product) => [
                'value' => $product->id,
                'label' => "{$product->name} ({$product->code})",
            ]);
    }

    private function categoryOptions()
    {
        return ProductCategory::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($cat) => [
                'value' => $cat->id,
                'label' => $cat->name,
            ]);
    }
}
