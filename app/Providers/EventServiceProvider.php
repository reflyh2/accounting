<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Asset;
use App\Models\AssetFinancingPayment;
use App\Models\AssetRentalPayment;
use App\Observers\AssetObserver;
use App\Observers\AssetFinancingPaymentObserver;
use App\Observers\AssetRentalPaymentObserver;

class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Asset::observe(AssetObserver::class);
        AssetFinancingPayment::observe(AssetFinancingPaymentObserver::class);
        AssetRentalPayment::observe(AssetRentalPaymentObserver::class);
    }
} 