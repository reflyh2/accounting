<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Journal extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $journalTypeCode = self::journalTypesCode()[$model->journal_type];
            $journalDate = date('y', strtotime($model->date));
            $lastJournal = self::where('journal_type', $model->journal_type)
                                ->whereYear('date', date('Y', strtotime($model->date)))
                                ->where('branch_id', $model->branch_id)
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
        ];
    }
}
