<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class ShippingProvider extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($provider) {
            if (empty($provider->code)) {
                $provider->code = self::generateCode();
            }
            $provider->created_by = $provider->created_by ?? Auth::user()?->global_id;
        });

        static::updating(function ($provider) {
            $provider->updated_by = Auth::user()?->global_id;
        });
    }

    private static function generateCode(): string
    {
        $lastProvider = self::withTrashed()->orderBy('id', 'desc')->first();
        $nextNumber = $lastProvider ? ((int) substr($lastProvider->code, 2)) + 1 : 1;

        return 'SP'.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
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
