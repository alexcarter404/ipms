<?php

namespace App\Providers;

use App\Services\Invoicing\InternalInvoicingProvider;
use App\Services\Invoicing\InvoicingProvider;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The invoicing seam: register an external driver (Xero, Stripe, …)
        // here without touching the WIP layer.
        $this->app->bind(InvoicingProvider::class, match (config('billing.invoicing_provider')) {
            default => InternalInvoicingProvider::class,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
