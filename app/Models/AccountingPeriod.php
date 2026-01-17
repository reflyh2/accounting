<?php

namespace App\Models;

use App\Exceptions\DocumentStateException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AccountingPeriod extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'start_date',
        'end_date',
        'status',
        'closed_by',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
    ];

    const STATUS_OPEN = 'open';
    const STATUS_SOFT_CLOSED = 'soft_closed';
    const STATUS_CLOSED = 'closed';

    /**
     * Get the company.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who closed this period.
     */
    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by', 'global_id');
    }

    /**
     * Find the period for a given date and company.
     */
    public static function findForDate(Carbon|string $date, int $companyId): ?self
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        return static::where('company_id', $companyId)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }

    /**
     * Check if a date is in an open period.
     */
    public static function isDateOpen(Carbon|string $date, int $companyId): bool
    {
        $period = static::findForDate($date, $companyId);

        return $period && $period->isOpen();
    }

    /**
     * Validate that posting is allowed for a given date.
     * Throws exception if not allowed.
     */
    public static function validatePostingAllowed(Carbon|string $date, int $companyId, bool $allowSoftClosed = false): void
    {
        $period = static::findForDate($date, $companyId);

        if (!$period) {
            throw new DocumentStateException('No accounting period found for the specified date.');
        }

        if ($period->isClosed()) {
            throw new DocumentStateException('Cannot post to a closed accounting period.');
        }

        if ($period->isSoftClosed() && !$allowSoftClosed) {
            throw new DocumentStateException('Accounting period is soft-closed. Special permission required.');
        }
    }

    /**
     * Check if the period is open.
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Check if the period is soft closed.
     */
    public function isSoftClosed(): bool
    {
        return $this->status === self::STATUS_SOFT_CLOSED;
    }

    /**
     * Check if the period is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Soft close the period.
     */
    public function softClose(?string $notes = null): self
    {
        $this->update([
            'status' => self::STATUS_SOFT_CLOSED,
            'notes' => $notes,
        ]);

        return $this;
    }

    /**
     * Close the period.
     */
    public function close(?string $notes = null): self
    {
        $user = Auth::user();

        $this->update([
            'status' => self::STATUS_CLOSED,
            'closed_by' => $user?->global_id,
            'closed_at' => now(),
            'notes' => $notes,
        ]);

        return $this;
    }

    /**
     * Reopen the period.
     */
    public function reopen(?string $notes = null): self
    {
        $this->update([
            'status' => self::STATUS_OPEN,
            'closed_by' => null,
            'closed_at' => null,
            'notes' => $notes,
        ]);

        return $this;
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to filter by open status.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Scope to filter by closed status.
     */
    public function scopeClosed($query)
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    /**
     * Scope to get periods that allow posting.
     */
    public function scopeAllowPosting($query, bool $includeSoftClosed = false)
    {
        if ($includeSoftClosed) {
            return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_SOFT_CLOSED]);
        }

        return $query->where('status', self::STATUS_OPEN);
    }
}
