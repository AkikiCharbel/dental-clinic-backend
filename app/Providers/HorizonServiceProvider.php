<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Configure Horizon night mode based on user preference or system setting
        // Horizon::night();
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            // In local environment, allow access
            if (app()->environment('local')) {
                return true;
            }

            // Check if user has admin permissions (using Spatie Permission)
            if ($user && method_exists($user, 'hasRole')) {
                return $user->hasRole(['super-admin', 'admin']);
            }

            return false;
        });
    }
}
