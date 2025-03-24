<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssetMaintenanceRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_id',
        'maintenance_type_id',
        'maintenance_date',
        'cost',
        'credited_account_id',
        'journal_id',
        'payment_status',
        'payment_date',
        'description',
        'performed_by',
        'next_maintenance_date',
        'completed_at',
    ];

    protected $casts = [
        'maintenance_date' => 'date',
        'payment_date' => 'date',
        'next_maintenance_date' => 'date',
        'cost' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the asset this maintenance record belongs to.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Get the maintenance type for this record.
     */
    public function maintenanceType(): BelongsTo
    {
        return $this->belongsTo(AssetMaintenanceType::class, 'maintenance_type_id');
    }

    /**
     * Get the account credited for this maintenance.
     */
    public function creditedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'credited_account_id');
    }

    /**
     * Get the journal entry for this maintenance payment.
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * Determine if this maintenance record has been paid.
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Determine if this maintenance record is pending payment.
     */
    public function isPending(): bool
    {
        return $this->payment_status === 'pending';
    }

    /**
     * Determine if this maintenance record has been completed.
     */
    public function isCompleted(): bool
    {
        return !is_null($this->completed_at);
    }
} 