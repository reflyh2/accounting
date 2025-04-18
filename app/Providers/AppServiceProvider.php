<?php

namespace App\Providers;

use App\Models\Asset;
use App\Observers\AssetObserver;
use App\Models\AssetRentalPayment;
use App\Models\AssetFinancingPayment;
use App\Models\AssetDepreciationEntry;
use App\Models\AssetMaintenanceRecord;
use Illuminate\Support\ServiceProvider;
use App\Observers\AssetRentalPaymentObserver;
use App\Observers\AssetFinancingPaymentObserver;
use App\Observers\AssetDepreciationObserver;
use App\Observers\AssetMaintenanceRecordObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    }
}
