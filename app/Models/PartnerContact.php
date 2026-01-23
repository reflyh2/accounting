<?php

namespace App\Models;

use App\Traits\HasPartnerContactAccessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class PartnerContact extends Model
{
    use HasFactory, HasPartnerContactAccessScope;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (Auth::check() && !$model->created_by) {
                $model->created_by = Auth::user()->global_id;
            }
        });
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }
}