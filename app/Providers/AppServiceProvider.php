<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register null tenant binding as default (resolved by middleware)
        $this->app->bind('currentTenant', fn () => null);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureModels();
        $this->configureRateLimiting();
        $this->configureDatabase();
        $this->configureAuthorization();
    }

    /**
     * Configure authorization gates and policies.
     */
    private function configureAuthorization(): void
    {
        // Give admin users all permissions
        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->isAdmin()) {
                return true;
            }

            return null; // Continue to normal permission checks
        });
    }

    /**
     * Configure Eloquent model strict mode and settings.
     */
    private function configureModels(): void
    {
        // Enable strict mode in non-production environments
        Model::shouldBeStrict(! $this->app->isProduction());

        // Always prevent silently discarding attributes
        Model::preventSilentlyDiscardingAttributes();

        // Prevent lazy loading in non-production (catch N+1 queries)
        Model::preventLazyLoading(! $this->app->isProduction());
    }

    /**
     * Configure rate limiters for the application.
     */
    private function configureRateLimiting(): void
    {
        // Default API rate limiter
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();

            // Authenticated users get higher limits
            if ($user) {
                return Limit::perMinute(120)->by($user->id);
            }

            // Unauthenticated requests limited by IP
            return Limit::perMinute(60)->by($request->ip());
        });

        // Strict rate limiter for sensitive operations (login, registration, password reset)
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Rate limiter for file uploads
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Rate limiter for exports/reports
        RateLimiter::for('exports', function (Request $request) {
            return Limit::perHour(20)->by($request->user()?->id ?: $request->ip());
        });

        // Per-tenant rate limiter
        RateLimiter::for('tenant', function (Request $request) {
            $tenantId = tenant_id() ?? $request->header('X-Tenant-ID');

            if ($tenantId) {
                return Limit::perMinute(1000)->by('tenant:'.$tenantId);
            }

            return Limit::perMinute(60)->by($request->ip());
        });
    }

    /**
     * Configure database settings.
     */
    private function configureDatabase(): void
    {
        // Log slow queries in non-production environments
        if (! $this->app->isProduction()) {
            DB::listen(function (QueryExecuted $query): void {
                if ($query->time > 100) { // 100ms threshold
                    logger()->warning('Slow query detected', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time' => $query->time.'ms',
                    ]);
                }
            });
        }
    }
}
