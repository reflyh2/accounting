<?php

namespace App\Providers;

use App\Models\Asset;
use App\Models\AssetDepreciationSchedule;
use App\Models\AssetMaintenance;
use App\Models\InventoryTransaction;
use App\Models\Journal;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Services\Accounting\Publisher\AccountingEventPublisherFactory;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        // Register morph map for polymorphic relationships
        Relation::morphMap([
            // CostEntrySource types
            'purchase_invoice' => PurchaseInvoice::class,
            'journal' => Journal::class,
            'inventory_issue' => InventoryTransaction::class,
            'asset_depreciation' => AssetDepreciationSchedule::class,
            'sales_invoice' => SalesInvoice::class,
            'asset_maintenance' => AssetMaintenance::class,
            // CostObjectType types
            'product' => Product::class,
            'product_variant' => ProductVariant::class,
            'asset_instance' => Asset::class,
        ]);
    }
}

