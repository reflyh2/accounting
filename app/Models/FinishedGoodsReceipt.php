<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinishedGoodsReceipt extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $receiptDate = date('y', strtotime($model->receipt_date ?? now()));
            $lastReceipt = self::whereYear('receipt_date', date('Y', strtotime($model->receipt_date ?? now())))
                          ->where('branch_id', $model->branch_id)
                          ->withTrashed()
                          ->orderBy('receipt_number', 'desc')
                          ->first();
            $lastNumber = $lastReceipt ? intval(substr($lastReceipt->receipt_number, -5)) : 0;
            $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
            $model->receipt_number = 'FGR.' . str_pad($model->company_id, 2, '0', STR_PAD_LEFT) . str_pad($model->branch_id, 3, '0', STR_PAD_LEFT) . $receiptDate . '.' . $newNumber;
        });

        static::addGlobalScope('userFinishedGoodsReceipts', function ($builder) {
            if (Auth::check()) {
                $user = User::find(Auth::user()->global_id);

                if ($user->roles->whereIn('access_level', ['company', 'branch_group', 'branch'])->isNotEmpty()) {
                    $builder->whereHas('branch');
                } else {
                    $builder->where('finished_goods_receipts.user_global_id', $user->global_id);
                }
            }
        });
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_global_id', 'global_id');
    }

    public function finishedProductVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'finished_product_variant_id');
    }

    public function locationTo()
    {
        return $this->belongsTo(Location::class, 'location_to_id');
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }

    public function inventoryTransaction()
    {
        return $this->belongsTo(InventoryTransaction::class);
    }

    public function lot()
    {
        return $this->belongsTo(Lot::class);
    }

    public function serial()
    {
        return $this->belongsTo(Serial::class);
    }

    protected $casts = [
        'receipt_date' => 'date',
        'posted_at' => 'datetime',
        'quantity_good' => 'decimal:6',
        'quantity_scrap' => 'decimal:6',
        'total_material_cost' => 'decimal:6',
        'labor_cost' => 'decimal:6',
        'overhead_cost' => 'decimal:6',
        'total_cost' => 'decimal:6',
        'unit_cost' => 'decimal:6',
    ];

    public static function statuses()
    {
        return [
            'draft' => 'Draft',
            'posted' => 'Posted',
        ];
    }
}