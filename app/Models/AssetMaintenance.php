<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\AvoidDuplicateConstraintOnSoftDelete;

class AssetMaintenance extends Model
{
    use HasFactory, SoftDeletes, AvoidDuplicateConstraintOnSoftDelete;

    protected $guarded = [];

    protected $casts = [
        'maintenance_date' => 'date',
        'labor_cost' => 'decimal:4',
        'parts_cost' => 'decimal:4',
        'external_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $lastMaintenance = self::where('company_id', $model->company_id)
                            ->where('branch_id', $model->branch_id)
                            ->orderBy('created_at', 'desc')
                            ->first();
            $lastNumber = $lastMaintenance ? intval(substr($lastMaintenance->code, -6)) : 0;
            $newNumber = str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
            $model->code = 'MTN' . str_pad($model->company_id, 2, '0', STR_PAD_LEFT) . str_pad($model->branch_id, 3, '0', STR_PAD_LEFT) . $newNumber;
        });

        static::saving(function ($model) {
            $model->total_cost = ($model->labor_cost ?? 0) + ($model->parts_cost ?? 0) + ($model->external_cost ?? 0);
        });

        static::addGlobalScope('userMaintenances', function ($builder) {
            if (Auth::check()) {
                $user = User::find(Auth::user()->global_id);

                // Skip scope if user doesn't exist in tenant DB yet (e.g., during seeding)
                if (!$user) {
                    return;
                }

                if ($user->roles->whereIn('access_level', ['company', 'branch_group', 'branch'])->isNotEmpty()) {
                    $builder->whereHas('branch');
                } else {
                    $builder->where('created_by', $user->global_id);
                }
            }
        });
    }

    public function getDuplicateAvoidColumns(): array
    {
        return ['code'];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────────

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Partner::class, 'vendor_id');
    }

    public function costEntry()
    {
        return $this->belongsTo(CostEntry::class);
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by', 'global_id');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Static Helpers
    // ─────────────────────────────────────────────────────────────────────────

    public static function maintenanceTypes(): array
    {
        return [
            'repair' => 'Perbaikan',
            'service' => 'Servis',
            'inspection' => 'Inspeksi',
            'upgrade' => 'Upgrade',
            'other' => 'Lainnya',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'draft' => 'Draft',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
        ];
    }
}
