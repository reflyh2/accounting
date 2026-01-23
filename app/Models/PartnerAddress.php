<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class PartnerAddress extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = Auth::user()->global_id;
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::user()->global_id;
            }
        });
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by', 'global_id');
    }
}
