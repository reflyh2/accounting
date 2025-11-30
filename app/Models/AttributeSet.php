<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeSet extends Model
{
    protected $guarded = [];

    public function attributes()
    {
        return $this->hasMany(AttributeDef::class, 'attribute_set_id');
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'attribute_set_company');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'global_id');
    }
}


