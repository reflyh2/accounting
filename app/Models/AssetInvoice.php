<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use App\Traits\AvoidDuplicateConstraintOnSoftDelete;

class AssetInvoice extends Model
{
    use HasFactory, SoftDeletes, AvoidDuplicateConstraintOnSoftDelete;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Generate invoice number (Example: AI.YY.BRANCH.NNNNN)
            // AI = Asset Invoice prefix based on type? For now, 'AI'
            // YY = Year
            // BRANCH = Branch ID padded
            // NNNNN = Sequence
            $invoicePrefix = match($model->type) {
                'purchase' => 'AB', // Asset Purchase
                'rental' => 'AR',   // Asset Rental
                'sales' => 'AS',    // Asset Sales
                default => 'AI'     // Default to Asset Invoice
            };
            $invoiceYear = date('y', strtotime($model->invoice_date));
            $paddedBranchId = str_pad($model->branch_id, 3, '0', STR_PAD_LEFT); // Assuming branch_id exists

            $lastInvoice = self::where('branch_id', $model->branch_id)
                               ->whereYear('invoice_date', date('Y', strtotime($model->invoice_date)))
                               ->where('type', $model->type) // Sequence per type?
                               ->orderBy('number', 'desc')
                               ->withTrashed() // Consider deleted invoices for numbering
                               ->first();

            $lastNumber = $lastInvoice ? intval(substr($lastInvoice->number, -5)) : 0;
            $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);

            $model->number = $invoicePrefix . '.' . $invoiceYear . '.' . $paddedBranchId . '.' . $newNumber;

            if (Auth::check() && !$model->created_by) {
                $model->created_by = Auth::user()->global_id;
            }
        });

        static::updating(function ($model) {
            if (Auth::check() && !$model->updated_by) {
                $model->updated_by = Auth::user()->global_id;
            }
        });

        // Potentially add a global scope for branch/user access if needed, similar to Journal
        // static::addGlobalScope('userScope', function ($builder) { ... });
    }

    public function getDuplicateAvoidColumns(): array
    {
        return ['number'];
    }

    public function assetInvoiceDetails()
    {
        return $this->hasMany(AssetInvoiceDetail::class);
    }

    public function assets()
    {
        return $this->hasManyThrough(Asset::class, AssetInvoiceDetail::class, 'asset_invoice_id', 'id', 'id', 'asset_id');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'global_id');
    }

    public static function statusOptions()
    {
        return [
            'open' => 'Aktif',
            'partially_paid' => 'Dibayar Sebagian',
            'paid' => 'Lunas',
            'overdue' => 'Jatuh Tempo',
            'cancelled' => 'Dibatalkan',
            'defaulted' => 'Gagal Bayar',
            'closed' => 'Selesai',
            'financed' => 'Dibiayai',
        ];
    }
} 