<?php

namespace App\Models;

use App\Enums\AccountingEventStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class AccountingEventLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'dispatched_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            $log->status ??= AccountingEventStatus::QUEUED->value;
        });
    }

    public function markSent(): void
    {
        $this->forceFill([
            'status' => AccountingEventStatus::SENT->value,
            'last_error' => null,
            'dispatched_at' => Carbon::now(),
        ])->save();
    }

    public function markFailed(string $message): void
    {
        $this->forceFill([
            'status' => AccountingEventStatus::FAILED->value,
            'last_error' => $message,
        ])->save();
    }
}


