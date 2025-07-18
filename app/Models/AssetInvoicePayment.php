<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Traits\AvoidDuplicateConstraintOnSoftDelete;

class AssetInvoicePayment extends Model
{
    use HasFactory, SoftDeletes, AvoidDuplicateConstraintOnSoftDelete;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Generate payment number (Example: AIP.YY.BRANCH.NNNNN)
            $paymentPrefix = match($model->type) {
                'purchase' => 'APP', // Asset Purchase Payment
                'rental' => 'ARP',   // Asset Rental Payment
                'sales' => 'ASP',    // Asset Sales Payment
                default => 'AIP'     // Default to Asset Invoice Payment
            };
            
            $paymentYear = date('y', strtotime($model->payment_date));
            
            // Find branch from partner's asset invoices or use a default approach
            $branch = null;
            $lastPayment = self::where('type', $model->type)
                              ->whereYear('payment_date', date('Y', strtotime($model->payment_date)))
                              ->orderBy('number', 'desc')
                              ->withTrashed()
                              ->first();

            $lastNumber = $lastPayment ? intval(substr($lastPayment->number, -5)) : 0;
            $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);

            $model->number = $paymentPrefix . '.' . $paymentYear . '.' . $newNumber;

            if (Auth::check() && !$model->created_by) {
                $model->created_by = Auth::user()->global_id;
            }
        });

        static::updating(function ($model) {
            if (Auth::check() && !$model->updated_by) {
                $model->updated_by = Auth::user()->global_id;
            }
        });

        static::addGlobalScope('userPayments', function ($builder) {
            if (Auth::check()) {
                $user = User::find(Auth::user()->global_id);

                if ($user && $user->roles->whereIn('access_level', ['company', 'branch_group', 'branch'])->isNotEmpty()) {
                    // Users with company/branch access can see all payments
                    return;
                } else {
                    // Regular users can only see their own payments
                    $builder->where('asset_invoice_payments.created_by', $user->global_id);
                }
            }
        });
    }

    public function getDuplicateAvoidColumns(): array
    {
        return ['number'];
    }

    public function allocations()
    {
        return $this->hasMany(AssetInvoicePaymentAllocation::class);
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

    public function sourceAccount()
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    public function destinationBankAccount()
    {
        return $this->belongsTo(PartnerBankAccount::class, 'destination_bank_account_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'global_id');
    }

    public static function getPaymentMethods()
    {
        return [
            'cash' => 'Tunai',
            'check' => 'Cek',
            'credit_card' => 'Kartu Kredit',
            'bank_transfer' => 'Transfer Bank',
            'other' => 'Lainnya'
        ];
    }

    public static function getTypes()
    {
        return [
            'purchase' => 'Pembelian Aset',
            'rental' => 'Sewa Aset',
            'sales' => 'Penjualan Aset'
        ];
    }
} 