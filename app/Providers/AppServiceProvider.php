<?php

namespace App\Providers;

use App\Services\Invoicing\InternalInvoicingProvider;
use App\Services\Invoicing\InvoicingProvider;
use App\Enums\AccessRole;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
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

        // Access control: admins own configuration; finance shares the
        // billing settings; read-only users change nothing (enforced by
        // middleware); walled clients only show to their wall.
        Gate::define('manage-settings', fn (User $user) => $user->isAdmin());
        Gate::define('manage-users', fn (User $user) => $user->isAdmin());
        Gate::define('manage-billing-settings', fn (User $user) => $user->isAdmin()
            || $user->access_role === AccessRole::Finance);
        Gate::define('view-client', fn (User $user, Client $client) => $client->isVisibleTo($user));
    }
}
