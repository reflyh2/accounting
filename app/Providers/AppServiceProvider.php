<?php

namespace App\Providers;

use App\Services\Accounting\Publisher\AccountingEventPublisherFactory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AccountingEventPublisherFactory::class, function ($app) {
            return new AccountingEventPublisherFactory(config('accounting.events', []));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register event subscribers that use the subscribe() method
    }
}
