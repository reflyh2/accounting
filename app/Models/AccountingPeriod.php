<?php

namespace App\Models;

use App\Exceptions\DocumentStateException;
use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class AccountingPeriod extends Model
{
    use Auditable;

    /**
     * Fields to audit for this model.
     */
    protected $auditable = ['status'];

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
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'closed_at' => 'datetime',
    ];

    const STATUS_OPEN = 'open';

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
        $dateStr = $date instanceof Carbon ? $date->format('Y-m-d') : Carbon::parse($date)->format('Y-m-d');

        return static::where('company_id', $companyId)
            ->where('start_date', '<=', $dateStr)
            ->where('end_date', '>=', $dateStr)
            ->first();
    }

    /**
     * Check if a date is in an open period.
     */
    public static function isDateOpen(Carbon|string $date, int $companyId): bool
    {
        $period = static::findForDate($date, $companyId);

        return ! $period || $period->isOpen();
    }

    /**
     * Validate that posting is allowed for a given date.
     * Throws exception if not allowed.
     */
    public static function validatePostingAllowed(Carbon|string $date, int $companyId): void
    {
        $period = static::findForDate($date, $companyId);

        if ($period && $period->isClosed()) {
            throw new DocumentStateException('Tidak dapat melakukan transaksi/perubahan pada periode akuntansi yang sudah ditutup.');
        }
    }

    /**
     * Validate sequential closing.
     * Ensures preceding month for the company is closed first.
     */
    public static function validateSequentialClose(int $companyId, Carbon|string $startDate): void
    {
        $startCarbon = $startDate instanceof Carbon ? $startDate->copy() : Carbon::parse($startDate);
        $startDateStr = $startCarbon->format('Y-m-d');

        // Check if there are any unclosed prior periods in DB
        $unclosedPrior = static::where('company_id', $companyId)
            ->where('end_date', '<', $startDateStr)
            ->where('status', self::STATUS_OPEN)
            ->orderBy('start_date', 'asc')
            ->first();

        if ($unclosedPrior) {
            throw new DocumentStateException("Periode akuntansi bulan sebelumnya ({$unclosedPrior->name}) harus ditutup terlebih dahulu secara berurutan.");
        }

        // If company has any periods created, ensure the immediate previous month exists and is closed
        $hasAnyPeriods = static::where('company_id', $companyId)->exists();
        if ($hasAnyPeriods) {
            $previousMonthStart = $startCarbon->copy()->subMonth()->startOfMonth()->format('Y-m-d');

            $previousPeriod = static::where('company_id', $companyId)
                ->where('start_date', $previousMonthStart)
                ->first();

            if (! $previousPeriod) {
                $monthNames = [
                    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
                ];
                $prevMonthNum = (int) $startCarbon->copy()->subMonth()->format('n');
                $prevYearNum = (int) $startCarbon->copy()->subMonth()->format('Y');
                $prevName = $monthNames[$prevMonthNum].' '.$prevYearNum;

                throw new DocumentStateException("Periode akuntansi bulan sebelumnya ({$prevName}) belum ditutup. Penutupan periode harus dilakukan secara berurutan.");
            }

            if ($previousPeriod->isOpen()) {
                throw new DocumentStateException("Periode akuntansi bulan sebelumnya ({$previousPeriod->name}) harus ditutup terlebih dahulu secara berurutan.");
            }
        }
    }

    /**
     * Validate sequential reopening (deletion).
     * Ensures newer closed periods are reopened/deleted first.
     */
    public static function validateSequentialReopen(int $companyId, Carbon|string $startDate): void
    {
        $startDateStr = $startDate instanceof Carbon ? $startDate->format('Y-m-d') : Carbon::parse($startDate)->format('Y-m-d');

        $newerClosedPeriod = static::where('company_id', $companyId)
            ->where('start_date', '>', $startDateStr)
            ->where('status', self::STATUS_CLOSED)
            ->orderBy('start_date', 'desc')
            ->first();

        if ($newerClosedPeriod) {
            throw new DocumentStateException("Periode akuntansi setelahnya ({$newerClosedPeriod->name}) harus dibuka/dihapus terlebih dahulu secara berurutan.");
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
     * Check if the period is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Close the period.
     */
    public function close(?string $notes = null): self
    {
        self::validateSequentialClose($this->company_id, $this->start_date);

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
}
