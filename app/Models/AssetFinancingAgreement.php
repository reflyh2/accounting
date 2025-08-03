<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\AvoidDuplicateConstraintOnSoftDelete;
use App\Services\AssetFinancing\ScheduleService;

class AssetFinancingAgreement extends Model
{
    use HasFactory, SoftDeletes, AvoidDuplicateConstraintOnSoftDelete;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $year = date('Y', strtotime($model->agreement_date));
            $lastAgreement = self::whereYear('agreement_date', $year)
                                ->orderBy('number', 'desc')
                                ->first();
            
            $lastNumber = 0;
            if ($lastAgreement) {
                $parts = explode('/', $lastAgreement->number);
                $lastNumber = intval(end($parts));
            }
            
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            $model->number = "AFA/{$year}/{$newNumber}";
            
            if (Auth::check()) {
                $model->created_by = Auth::user()->global_id;
            }
        });

        static::created(function ($model) {
            // Update the asset invoice status to financed when the agreement is created
            if ($model->assetInvoice) {
                $model->assetInvoice->update(['status' => 'financed']);
            }

            // Generate the schedule
            if ($model->interest_calculation_method) {
                (new ScheduleService())->generate($model);
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::user()->global_id;
            }

            // Handle asset invoice status changes when the invoice is changed
            if ($model->isDirty('asset_invoice_id')) {
                $oldInvoiceId = $model->getOriginal('asset_invoice_id');
                $newInvoiceId = $model->asset_invoice_id;

                // Revert the old invoice status
                if ($oldInvoiceId) {
                    $oldInvoice = AssetInvoice::find($oldInvoiceId);
                    if ($oldInvoice) {
                        $totalPaid = \App\Models\AssetInvoicePaymentAllocation::where('asset_invoice_id', $oldInvoiceId)
                            ->sum('allocated_amount');

                        if ($totalPaid >= $oldInvoice->total_amount) {
                            $oldInvoice->update(['status' => 'paid']);
                        } elseif ($totalPaid > 0) {
                            $oldInvoice->update(['status' => 'partially_paid']);
                        } else {
                            $oldInvoice->update(['status' => 'open']);
                        }
                    }
                }

                // Update the new invoice status to financed
                if ($newInvoiceId) {
                    $newInvoice = AssetInvoice::find($newInvoiceId);
                    if ($newInvoice) {
                        $newInvoice->update(['status' => 'financed']);
                    }
                }
            }

            // Regenerate the schedule if relevant fields have changed
            if ($model->isDirty('total_amount', 'interest_rate', 'start_date', 'end_date', 'payment_frequency', 'interest_calculation_method')) {
                if ($model->interest_calculation_method) {
                    (new ScheduleService())->generate($model);
                }
            }
        });

        static::deleted(function ($model) {
            // Revert the asset invoice status when the agreement is deleted
            if ($model->assetInvoice) {
                $totalPaid = \App\Models\AssetInvoicePaymentAllocation::where('asset_invoice_id', $model->asset_invoice_id)
                    ->sum('allocated_amount');

                if ($totalPaid >= $model->assetInvoice->total_amount) {
                    $model->assetInvoice->update(['status' => 'paid']);
                } elseif ($totalPaid > 0) {
                    $model->assetInvoice->update(['status' => 'partially_paid']);
                } else {
                    $model->assetInvoice->update(['status' => 'open']);
                }
            }

            // Delete the schedule
            $model->schedules()->delete();
        });

        static::addGlobalScope('userAgreements', function ($builder) {
            if (Auth::check()) {
                $user = User::find(Auth::user()->global_id);

                if ($user->roles->whereIn('access_level', ['company', 'branch_group', 'branch'])->isNotEmpty()) {
                    // User has company/branch access - they can see agreements
                } else {
                    $builder->where('created_by', $user->global_id);
                }
            }
        });
    }

    public function getDuplicateAvoidColumns(): array
    {
        return ['number'];
    }

    public function creditor()
    {
        return $this->belongsTo(Partner::class, 'creditor_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function assetInvoice()
    {
        return $this->belongsTo(AssetInvoice::class);
    }

    public function getAssetAttribute()
    {
        return $this->assetInvoice->assets->first();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by', 'global_id');
    }

    public static function statusOptions()
    {
        return [
            'active' => 'Aktif',
            'pending' => 'Pending',
            'closed' => 'Selesai',
            'defaulted' => 'Gagal Bayar',
            'cancelled' => 'Dibatalkan',
        ];
    }

    public static function paymentFrequencyOptions()
    {
        return [
            'monthly' => 'Bulanan',
            'quarterly' => 'Kuartalan',
            'annually' => 'Tahunan',
        ];
    }

    public function getStatusLabelAttribute()
    {
        return self::statusOptions()[$this->status] ?? $this->status;
    }

    public function getPaymentFrequencyLabelAttribute()
    {
        return self::paymentFrequencyOptions()[$this->payment_frequency] ?? $this->payment_frequency;
    }

    public function getInterestCalculationMethodLabelAttribute()
    {
        return self::interestCalculationMethodOptions()[$this->interest_calculation_method] ?? $this->interest_calculation_method;
    }

    public function schedules()
    {
        return $this->hasMany(AssetFinancingSchedule::class);
    }

    public static function interestCalculationMethodOptions()
    {
        return [
            'annuity' => 'Anuitas',
            'straight_line' => 'Angsuran Pokok Tetap',
            'flat_rate' => 'Flat',
            'sum_of_digits' => 'Jumlah Angka Tahunan',
            'interest_only' => 'Bullet',
            'simple_interest_daily_accrual' => 'Bunga Harian',
        ];
    }
} 