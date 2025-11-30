<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxRule extends Model
{
    protected $guarded = [];

    protected $casts = [
        'tax_inclusive' => 'boolean',
        'b2b_applicable' => 'boolean',
        'reverse_charge' => 'boolean',
        'export_zero_rate' => 'boolean',
        'conditions_json' => 'array',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'rate_value' => 'decimal:6',
        'threshold_amount' => 'decimal:6',
    ];

    public function taxCategory()
    {
        return $this->belongsTo(TaxCategory::class);
    }

    public function jurisdiction()
    {
        return $this->belongsTo(TaxJurisdiction::class, 'tax_jurisdiction_id');
    }

    public function component()
    {
        return $this->belongsTo(TaxComponent::class, 'tax_component_id');
    }

    public function perUnitUom()
    {
        return $this->belongsTo(Uom::class, 'per_unit_uom_id');
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


