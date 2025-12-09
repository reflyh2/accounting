<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlEventConfigurationLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'gl_event_configuration_id',
        'role',
        'direction',
        'account_id',
    ];

    public function glEventConfiguration()
    {
        return $this->belongsTo(GlEventConfiguration::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
