<?php

namespace App\Providers;

use App\Models\Asset;
use App\Observers\AssetObserver;
use App\Models\AssetRentalPayment;
use App\Models\AssetFinancingPayment;
use App\Models\AssetDepreciationEntry;
use Illuminate\Support\ServiceProvider;
use App\Observers\AssetRentalPaymentObserver;
use App\Observers\AssetFinancingPaymentObserver;
use App\Observers\AssetDepreciationObserver;

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
        Asset::observe(AssetObserver::class);
        AssetFinancingPayment::observe(AssetFinancingPaymentObserver::class);
        AssetRentalPayment::observe(AssetRentalPaymentObserver::class);
        AssetDepreciationEntry::observe(AssetDepreciationObserver::class);
    }
}
