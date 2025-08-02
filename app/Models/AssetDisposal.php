<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use App\Traits\AvoidDuplicateConstraintOnSoftDelete;

class AssetDisposal extends Model
{
    use HasFactory, SoftDeletes, AvoidDuplicateConstraintOnSoftDelete;

    protected $guarded = [];

    protected $casts = [
        'disposal_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $disposalYear = date('y', strtotime($model->disposal_date));
            $paddedBranchId = str_pad($model->branch_id, 3, '0', STR_PAD_LEFT);

            $lastDisposal = self::where('branch_id', $model->branch_id)
                              ->whereYear('disposal_date', date('Y', strtotime($model->disposal_date)))
                              ->orderBy('number', 'desc')
                              ->withTrashed()
                              ->first();

            $lastNumber = $lastDisposal ? intval(substr($lastDisposal->number, -5)) : 0;
            $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);

            $model->number = 'AD.' . $disposalYear . '.' . $paddedBranchId . '.' . $newNumber;

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

    public function assetDisposalDetails()
    {
        return $this->hasMany(AssetDisposalDetail::class);
    }

    public function assets()
    {
        return $this->hasManyThrough(Asset::class, AssetDisposalDetail::class, 'asset_disposal_id', 'id', 'id', 'asset_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    
    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'global_id');
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
    
    public static function disposalTypeOptions()
    {
        return [
            'scrap' => 'Dihapuskan (Scrap)',
            'donation' => 'Donasi',
            'theft' => 'Hilang (Theft)',
            'accident' => 'Kecelakaan',
            'write_off' => 'Hapus Buku (Write-off)',
            'other' => 'Lainnya',
        ];
    }
} 