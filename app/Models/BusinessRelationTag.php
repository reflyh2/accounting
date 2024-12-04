<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BusinessRelationTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_relation_id',
        'tag_name',
    ];

    public function businessRelation()
    {
        return $this->belongsTo(BusinessRelation::class);
    }
} 