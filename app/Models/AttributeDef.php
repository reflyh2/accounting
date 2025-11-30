<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeDef extends Model
{
    protected $guarded = [];

    protected $casts = [
        'options_json' => 'array',
        'is_required' => 'boolean',
        'is_variant_axis' => 'boolean',
    ];

    public function attributeSet()
    {
        return $this->belongsTo(AttributeSet::class);
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


