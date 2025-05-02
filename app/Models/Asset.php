<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'acquisition_date' => 'date',
        'depreciation_start_date' => 'date',
        'warranty_expiry' => 'date',
        'is_depreciable' => 'boolean',
        'is_amortizable' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $lastAsset = self::where('company_id', $model->company_id)
                            ->where('branch_id', $model->branch_id)
                            ->orderBy('created_at', 'desc')
                            ->first();
            $lastNumber = $lastAsset ? intval(substr($lastAsset->code, -6)) : 0;
            $newNumber = str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
            $model->code = 'AST' . str_pad($model->company_id, 2, '0', STR_PAD_LEFT) . str_pad($model->branch_id, 3, '0', STR_PAD_LEFT) . $newNumber;
        });

        static::addGlobalScope('userAssets', function ($builder) {
            if (Auth::check()) {
                $user = User::find(Auth::user()->global_id);

                if ($user->roles->whereIn('access_level', ['company', 'branch_group', 'branch'])->isNotEmpty()) {
                    $builder->whereHas('branch');
                } else {
                    $builder->where('created_by', $user->global_id);
                }
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function category()
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by', 'global_id');
    }

    public static function assetTypes()
    {
        return [
            'tangible' => 'Berwujud',
            'intangible' => 'Tidak Berwujud',
        ];
    }

    public static function acquisitionTypes()
    {
        return [
            'outright_purchase' => 'Pembelian Langsung',
            'financed_purchase' => 'Pembelian Kredit',
            'leased' => 'Sewa Guna Usaha',
            'rented' => 'Sewa',
        ];
    }

    public static function depreciationMethods()
    {
        return [
            'straight-line' => 'Garis Lurus',
            'declining-balance' => 'Saldo Menurun',
            'units-of-production' => 'Unit Produksi',
            'sum-of-years-digits' => 'Jumlah Angka Tahun',
            'no-depreciation' => 'Tanpa Penyusutan',
        ];
    }

    public static function statusOptions()
    {
        return [
            'active' => 'Aktif',
            'inactive' => 'Tidak Aktif',
            'disposed' => 'Dilepas',
            'sold' => 'Dijual',
            'scrapped' => 'Dibuang',
            'written_off' => 'Dihapusbukukan',
        ];
    }
} 