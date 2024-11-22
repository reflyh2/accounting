<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'parent_id',
        'level',
        'balance_type',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $creditBalanceTypes = [
                'hutang_usaha',
                'hutang_usaha_lainnya',
                'liabilitas_jangka_pendek',
                'liabilitas_jangka_panjang',
                'modal',
                'pendapatan',
                'pendapatan_lainnya',
            ];
            
            $model->level = $model->parent ? $model->parent->level + 1 : 0;
            $model->balance_type = in_array($model->type, $creditBalanceTypes) ? 'credit' : 'debit';

            if ($model->parent_id != null) {
                $model->parent()->update(['is_parent' => true]);
            }
        });

        static::updating(function ($model) {
            // Check if the parent_id has changed
            if ($model->isDirty('parent_id')) {
                // If there was an old parent, check if it still has children
                if ($model->getOriginal('parent_id')) {
                    $oldParent = Account::find($model->getOriginal('parent_id'));
                    if ($oldParent && $oldParent->children()->count() == 0) {
                        $oldParent->update(['is_parent' => false]);
                    }
                }

                // Set new parent's is_parent to true if it exists
                if ($model->parent_id) {
                    $model->parent()->update(['is_parent' => true]);
                }
            }
        });
    }

    protected static function booted()
    {
        static::addGlobalScope('userAccounts', function ($builder) {
            if (Auth::check()) {
                $user = User::find(Auth::user()->global_id);

                $companyIds = $user->branches->pluck('branchGroup.company_id')->unique();

                $builder->whereHas('companies', function ($query) use ($companyIds) {
                    $query->whereIn('company_id', $companyIds);
                });
            }
        });
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class);
    }

    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function currencies()
    {
        return $this->belongsToMany(Currency::class, 'account_currencies')
            ->withPivot('balance');
    }

    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function getAllDescendantIds()
    {
        $descendantIds = collect();

        $this->children->each(function ($child) use (&$descendantIds) {
            $descendantIds->push($child->id);
            $descendantIds = $descendantIds->merge($child->getAllDescendantIds());
        });

        return $descendantIds;
    }

    public function getBalanceForDateAndBranches($date, $branchIds, $companyIds = [], $currencyId = null)
    {
        $balance = 0;

        // Get all descendant account IDs, including the current account
        $accountIds = $this->getAllDescendantIds()->push($this->id);
        $query = JournalEntry::whereIn('account_id', $accountIds)
            ->whereHas('journal', function ($query) use ($date, $branchIds, $companyIds) {
                $query->whereDate('date', '<=', $date);
                
                if (!empty($companyIds)) {
                    $query->whereHas('branch.branchGroup', function ($q) use ($companyIds) {
                        $q->whereIn('company_id', $companyIds);
                    });
                }
                
                if (!empty($branchIds)) {
                    $query->whereIn('branch_id', $branchIds);
                }
            })
            ->when($currencyId, function ($query) use ($currencyId) {
                $query->where('currency_id', $currencyId);
            });

        $debitSum = (clone $query)->sum('primary_currency_debit');
        $creditSum = $query->sum('primary_currency_credit');

        if ($this->balance_type === 'debit') {
            $balance += $debitSum - $creditSum;
        } else {
            $balance += $creditSum - $debitSum;
        }

        return $balance;
    }

    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    public function getBalanceForDateAndBranchesAndCurrency($date, $branchIds, $companyIds = [], $currencyId)
    {
        $balance = 0;

        $query = JournalEntry::where('account_id', $this->id)
            ->where('currency_id', $currencyId)
            ->whereHas('journal', function ($query) use ($date, $branchIds, $companyIds) {
                $query->whereDate('date', '<=', $date);
                
                if (!empty($companyIds)) {
                    $query->whereHas('branch.branchGroup', function ($q) use ($companyIds) {
                        $q->whereIn('company_id', $companyIds);
                    });
                }
                
                if (!empty($branchIds)) {
                    $query->whereIn('branch_id', $branchIds);
                }
            });

        $debitSum = (clone $query)->sum('debit');
        $creditSum = $query->sum('credit');

        if ($this->balance_type === 'debit') {
            $balance += $debitSum - $creditSum;
        } else {
            $balance += $creditSum - $debitSum;
        }

        return $balance;
    }

    public function getBalanceForDateRange($startDate, $endDate, $branchIds, $companyIds = [])
    {
        $balance = 0;

        // Get all descendant account IDs, including the current account
        $accountIds = $this->getAllDescendantIds()->push($this->id);
        
        $query = JournalEntry::whereIn('account_id', $accountIds)
            ->whereHas('journal', function ($query) use ($startDate, $endDate, $branchIds, $companyIds) {
                $query->whereDate('date', '>=', $startDate)
                      ->whereDate('date', '<=', $endDate);
                
                if (!empty($companyIds)) {
                    $query->whereHas('branch.branchGroup', function ($q) use ($companyIds) {
                        $q->whereIn('company_id', $companyIds);
                    });
                }
                
                if (!empty($branchIds)) {
                    $query->whereIn('branch_id', $branchIds);
                }
            });

        $debitSum = (clone $query)->sum('primary_currency_debit');
        $creditSum = $query->sum('primary_currency_credit');

        if ($this->balance_type === 'debit') {
            $balance += $debitSum - $creditSum;
        } else {
            $balance += $creditSum - $debitSum;
        }

        return $balance;
    }
}
