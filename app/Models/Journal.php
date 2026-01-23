<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Journal extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $journalTypeCode = self::journalTypesCode()[$model->journal_type];
            $journalDate = date('y', strtotime($model->date));
            $lastJournal = self::withoutGlobalScope('userJournals')
                                ->where('journal_type', $model->journal_type)
                                ->whereYear('date', date('Y', strtotime($model->date)))
                                ->where('branch_id', $model->branch_id)
                                ->withTrashed()
                                ->orderBy('journal_number', 'desc')
                                ->first();
            $lastNumber = $lastJournal ? intval(substr($lastJournal->journal_number, -5)) : 0;
            $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
            $model->journal_number = $journalTypeCode . '.' . str_pad($model->branch->branchGroup->company_id, 2, '0', STR_PAD_LEFT) . str_pad($model->branch_id, 3, '0', STR_PAD_LEFT) . $journalDate . '.' . $newNumber;
        });

        static::addGlobalScope('userJournals', function ($builder) {
            $builder->whereHas('branch');
            
            if (Auth::check()) {
                $user = User::find(Auth::user()->global_id);

                // Skip scope if user doesn't exist in tenant DB yet (e.g., during seeding)
                if (!$user) {
                    return;
                }

                if ($user->roles->whereIn('access_level', ['company', 'branch_group', 'branch'])->isNotEmpty()) {
                    $builder->whereHas('branch');
                } else {
                    $builder->where('journals.user_global_id', $user->global_id);
                }
            }
        });
    }

    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_global_id', 'global_id');
    }    

    public static function journalTypesLabel()
    {
        return [
            'general' => 'Jurnal Umum',
            'sales' => 'Jurnal Penjualan',
            'purchase' => 'Jurnal Pembelian',
            'cash_receipt' => 'Jurnal Penerimaan Kas',
            'cash_payment' => 'Jurnal Pembayaran Kas',
            'retained_earnings' => 'Jurnal Laba Ditahan',
            'asset_purchase' => 'Jurnal Pembelian Aset',
            'asset_rental' => 'Jurnal Sewa Aset',
            'asset_sale' => 'Jurnal Penjualan Aset',
            'asset_transfer' => 'Jurnal Pengalihan Aset',
            'asset_disposal' => 'Jurnal Pelepasan Aset',
            'asset_financing_agreement' => 'Jurnal Perjanjian Pembiayaan Aset',
            'asset_financing_payment' => 'Jurnal Pembayaran Pembelian Aset',
            'asset_invoice_payment' => 'Jurnal Pembayaran Invoice Aset',
            'asset_depreciation' => 'Jurnal Penyusutan Aset',
            'asset_amortization' => 'Jurnal Amortisasi Aset',
            'account_payable' => 'Jurnal Hutang',
            'account_receivable' => 'Jurnal Piutang',
            'account_payable_payment' => 'Jurnal Pembayaran Hutang',
            'account_receivable_collection' => 'Jurnal Penerimaan Piutang',
            'internal_payable' => 'Jurnal Hutang Internal',
            'internal_receivable' => 'Jurnal Piutang Internal',
            'internal_payable_payment' => 'Jurnal Pembayaran Hutang / Piutang Internal',
            'internal_receivable_collection' => 'Jurnal Penerimaan Hutang / Piutang Internal',
        ];
    }

    public static function journalTypesCode()
    {
        return [
            'general' => 'GL',
            'sales' => 'SL',
            'purchase' => 'PU',
            'cash_receipt' => 'CR',
            'cash_payment' => 'CP',
            'retained_earnings' => 'RE',
            'asset_purchase' => 'AP',
            'asset_rental' => 'AR',
            'asset_sale' => 'AS',
            'asset_transfer' => 'AT',
            'asset_disposal' => 'AD',
            'asset_financing_agreement' => 'AFA',
            'asset_financing_payment' => 'AFP',
            'asset_invoice_payment' => 'AIP',
            'asset_depreciation' => 'ADP',
            'asset_amortization' => 'AAM',
            'account_payable' => 'ACP',
            'account_receivable' => 'ACR',
            'account_payable_payment' => 'APP',
            'account_receivable_collection' => 'ARC',
            'internal_payable' => 'IP',
            'internal_receivable' => 'IR',
            'internal_payable_payment' => 'IPP',
            'internal_receivable_collection' => 'IRC',
        ];
    }
}
