<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BusinessRelationCreditTerm extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_relation_id',
        'credit_limit',
        'used_credit',
        'payment_term_days',
        'payment_term_type',
        'notes',
    ];

    const PAYMENT_TERM_TYPES = [
        'net' => 'Net Days',
        'cod' => 'Cash on Delivery',
        'cbd' => 'Cash Before Delivery',
        'cia' => 'Cash in Advance',
        'eom' => 'End of Month',
    ];

    public function businessRelation()
    {
        return $this->belongsTo(BusinessRelation::class);
    }
} 