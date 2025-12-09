<?php

namespace App\Models;

use App\Enums\AccountingEventCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlEventConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'event_code',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function lines()
    {
        return $this->hasMany(GlEventConfigurationLine::class);
    }

    public function getEventCodeLabelAttribute(): string
    {
        return AccountingEventCode::from($this->event_code)->label();
    }
}
