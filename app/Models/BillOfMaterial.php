<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillOfMaterial extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $bomDate = date('y', strtotime($model->effective_date ?? now()));
            $lastBom = self::whereYear('effective_date', date('Y', strtotime($model->effective_date ?? now())))
                          ->where('company_id', $model->company_id)
                          ->withTrashed()
                          ->orderBy('bom_number', 'desc')
                          ->first();
            $lastNumber = $lastBom ? intval(substr($lastBom->bom_number, -5)) : 0;
            $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
            $model->bom_number = 'BOM.' . str_pad($model->company_id, 2, '0', STR_PAD_LEFT) . $bomDate . '.' . $newNumber;
        });

        static::addGlobalScope('userBoms', function ($builder) {
            if (Auth::check()) {
                $user = User::find(Auth::user()->global_id);

                if ($user->roles->whereIn('access_level', ['company', 'branch_group', 'branch'])->isNotEmpty()) {
                    $builder->whereHas('company');
                } else {
                    $builder->where('bill_of_materials.user_global_id', $user->global_id);
                }
            }
        });
    }

    public function bomLines()
    {
        return $this->hasMany(BillOfMaterialLine::class)->orderBy('line_number');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_global_id', 'global_id');
    }

    public function finishedProduct()
    {
        return $this->belongsTo(Product::class, 'finished_product_id');
    }

    public function finishedProductVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'finished_product_variant_id');
    }

    public function finishedUom()
    {
        return $this->belongsTo(Uom::class, 'finished_uom_id');
    }

    public static function bomStatuses()
    {
        return [
            'draft' => 'Draft',
            'active' => 'Active',
            'inactive' => 'Inactive',
        ];
    }

    public function getTotalComponentsAttribute()
    {
        return $this->bomLines()->count();
    }

    public function getTotalCostAttribute()
    {
        // This would be calculated based on component costs
        // For now, return 0 - will be implemented in cost layer
        return 0;
    }
}
