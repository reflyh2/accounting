<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BusinessRelationCustomField extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_relation_id',
        'field_name',
        'field_value',
    ];

    public function businessRelation()
    {
        return $this->belongsTo(BusinessRelation::class);
    }
} 