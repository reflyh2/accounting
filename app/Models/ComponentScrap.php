<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class ComponentScrap extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'scrap_quantity' => 'decimal:6',
        'scrap_date' => 'date',
        'is_backflush' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('userComponentScraps', function ($builder) {
            if (Auth::check()) {
                $user = User::find(Auth::user()->global_id);

                if ($user->roles->whereIn('access_level', ['company', 'branch_group', 'branch'])->isNotEmpty()) {
                    $builder->whereHas('workOrder.branch');
                } else {
                    $builder->whereHas('workOrder', function ($query) use ($user) {
                        $query->where('work_orders.user_global_id', $user->global_id);
                    });
                }
            }
        });
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function componentIssueLine()
    {
        return $this->belongsTo(ComponentIssueLine::class);
    }

    public function bomLine()
    {
        return $this->belongsTo(BillOfMaterialLine::class, 'bom_line_id');
    }

    public function componentProduct()
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }

    public function componentProductVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'component_product_variant_id');
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }

    public function finishedGoodsReceipt()
    {
        return $this->belongsTo(FinishedGoodsReceipt::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_global_id', 'global_id');
    }
}
