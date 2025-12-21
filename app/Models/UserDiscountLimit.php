<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDiscountLimit extends Model
{
    protected $guarded = [];

    protected $casts = [
        'max_discount_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_global_id', 'global_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productCategory()
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'global_id');
    }

    /**
     * Scope to filter active limits.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Determine the scope type of this limit.
     */
    public function getScopeTypeAttribute(): string
    {
        if ($this->product_id) {
            return 'product';
        }
        if ($this->product_category_id) {
            return 'category';
        }
        return 'global';
    }
}
