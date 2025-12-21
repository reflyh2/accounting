<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\UserDiscountLimit;

class UserDiscountLimitResolver
{
    /**
     * Resolve the applicable discount limit for a user.
     * Resolution order: Product -> Category -> Global
     *
     * @param string $userGlobalId
     * @param int|null $productId
     * @param int|null $categoryId If null and productId is set, we'll look up the category from product
     * @return float|null Returns max_discount_percent or null if unlimited
     */
    public function resolve(string $userGlobalId, ?int $productId = null, ?int $categoryId = null): ?float
    {
        // 1. Product-specific limit
        if ($productId) {
            $productLimit = UserDiscountLimit::query()
                ->active()
                ->where('user_global_id', $userGlobalId)
                ->where('product_id', $productId)
                ->first();

            if ($productLimit) {
                return (float) $productLimit->max_discount_percent;
            }

            // If categoryId not provided, look it up from product
            if ($categoryId === null) {
                $categoryId = Product::where('id', $productId)->value('product_category_id');
            }
        }

        // 2. Category-specific limit
        if ($categoryId) {
            $categoryLimit = UserDiscountLimit::query()
                ->active()
                ->where('user_global_id', $userGlobalId)
                ->whereNull('product_id')
                ->where('product_category_id', $categoryId)
                ->first();

            if ($categoryLimit) {
                return (float) $categoryLimit->max_discount_percent;
            }
        }

        // 3. Global limit (no product/category)
        $globalLimit = UserDiscountLimit::query()
            ->active()
            ->where('user_global_id', $userGlobalId)
            ->whereNull('product_id')
            ->whereNull('product_category_id')
            ->first();

        if ($globalLimit) {
            return (float) $globalLimit->max_discount_percent;
        }

        // No limit configured = unlimited
        return null;
    }

    /**
     * Validate if a discount is within the user's allowed limit.
     *
     * @param string $userGlobalId
     * @param int|null $productId
     * @param int|null $categoryId
     * @param float $discountPercent
     * @return bool True if discount is allowed, false if it exceeds the limit
     */
    public function validateDiscount(string $userGlobalId, ?int $productId, ?int $categoryId, float $discountPercent): bool
    {
        $limit = $this->resolve($userGlobalId, $productId, $categoryId);

        // No limit = unlimited
        if ($limit === null) {
            return true;
        }

        return $discountPercent <= $limit;
    }

    /**
     * Check if a document requires approval due to discount exceeding user's limit.
     *
     * @param string $userGlobalId
     * @param int|null $productId
     * @param int|null $categoryId
     * @param float $discountPercent
     * @return bool True if approval is required
     */
    public function requiresApproval(string $userGlobalId, ?int $productId, ?int $categoryId, float $discountPercent): bool
    {
        return !$this->validateDiscount($userGlobalId, $productId, $categoryId, $discountPercent);
    }
}
