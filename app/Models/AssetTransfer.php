<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use App\Traits\AvoidDuplicateConstraintOnSoftDelete;

class AssetTransfer extends Model
{
    use HasFactory, SoftDeletes, AvoidDuplicateConstraintOnSoftDelete;

    protected $guarded = [];

    protected $casts = [
        'transfer_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $transferPrefix = 'AT'; // Asset Transfer
            $transferYear = date('y', strtotime($model->transfer_date));
            $paddedBranchId = str_pad($model->from_branch_id, 3, '0', STR_PAD_LEFT);

            $lastTransfer = self::where('from_branch_id', $model->from_branch_id)
                              ->whereYear('transfer_date', date('Y', strtotime($model->transfer_date)))
                              ->orderBy('number', 'desc')
                              ->withTrashed()
                              ->first();

            $lastNumber = $lastTransfer ? intval(substr($lastTransfer->number, -5)) : 0;
            $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);

            $model->number = $transferPrefix . '.' . $transferYear . '.' . $paddedBranchId . '.' . $newNumber;

            if (Auth::check() && !$model->created_by) {
                $model->created_by = Auth::user()->global_id;
            }
        });

        static::updating(function ($model) {
            if (Auth::check() && !$model->updated_by) {
                $model->updated_by = Auth::user()->global_id;
            }
        });
    }

    public function getDuplicateAvoidColumns(): array
    {
        return ['number'];
    }

    public function assetTransferDetails()
    {
        return $this->hasMany(AssetTransferDetail::class);
    }

    public function fromCompany()
    {
        return $this->belongsTo(Company::class, 'from_company_id');
    }

    public function fromBranch()
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toCompany()
    {
        return $this->belongsTo(Company::class, 'to_company_id');
    }

    public function toBranch()
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function fromJournal()
    {
        return $this->belongsTo(Journal::class, 'from_journal_id');
    }

    public function toJournal()
    {
        return $this->belongsTo(Journal::class, 'to_journal_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by', 'global_id');
    }

    public function rejector()
    {
        return $this->belongsTo(User::class, 'rejected_by', 'global_id');
    }
    
    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by', 'global_id');
    }

    public static function statusOptions()
    {
        return [
            'draft' => 'Draft',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
        ];
    }
} 