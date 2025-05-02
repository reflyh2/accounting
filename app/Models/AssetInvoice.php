<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class AssetInvoice extends Model
{
    use HasFactory, SoftDeletes;

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
            $invoicePrefix = 'AI'; // Could be dynamic based on $model->type
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

    public function assetInvoiceDetails()
    {
        return $this->hasMany(AssetInvoiceDetail::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
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