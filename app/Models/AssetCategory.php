<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AssetCategory extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'description'];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'category_id');
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'asset_category_company')
            ->withTimestamps();
    }
} 