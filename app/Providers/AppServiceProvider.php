<?php

namespace App\Providers;

use App\Services\Execution\ExecutionAdapterRegistry;
use App\Services\Execution\TenantExecutionAdapterFactory;
use App\Services\RequestCommunication\Sms\NullSmsProvider;
use App\Services\RequestCommunication\Sms\SmsProvider;
use App\Services\RequestCommunication\Sms\TermiiSmsProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SmsProvider::class, function () {
            $provider = strtolower((string) config('services.sms.provider', 'placeholder'));

            return match ($provider) {
                'termii' => new TermiiSmsProvider(),
                default => new NullSmsProvider(),
            };
        });

        // Provider-agnostic execution adapters are resolved once and reused by orchestration layers.
        $this->app->singleton(ExecutionAdapterRegistry::class);
        $this->app->singleton(TenantExecutionAdapterFactory::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        // Support subdirectory installs like /flowdesk/public on XAMPP.
        $prefix = trim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');

        if ($prefix === '') {
            return;
        }

        Livewire::setScriptRoute(function ($handle) use ($prefix) {
            return Route::get('/'.$prefix.'/livewire/livewire.js', $handle);
        });

        Livewire::setUpdateRoute(function ($handle) use ($prefix) {
            return Route::post('/'.$prefix.'/livewire/update', $handle)
                ->middleware(['web']);
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('execution-webhooks', function (Request $request): Limit {
            $limit = max(1, (int) config('security.rate_limits.execution_webhooks_per_minute', 60));
            $provider = strtolower(trim((string) $request->route('provider', 'unknown')));

            return Limit::perMinute($limit)->by($provider.'|'.$request->ip());
        });

        RateLimiter::for('tenant-downloads', function (Request $request): Limit {
            $limit = max(1, (int) config('security.rate_limits.tenant_downloads_per_minute', 120));
            $user = $request->user();
            $routeName = (string) optional($request->route())->getName();
            $scope = $user?->company_id
                ? 'tenant:'.(int) $user->company_id
                : 'ip:'.$request->ip();

            return Limit::perMinute($limit)->by($scope.'|'.$routeName);
        });

        RateLimiter::for('tenant-exports', function (Request $request): Limit {
            $limit = max(1, (int) config('security.rate_limits.tenant_exports_per_minute', 40));
            $user = $request->user();
            $routeName = (string) optional($request->route())->getName();
            $scope = $user?->company_id
                ? 'tenant:'.(int) $user->company_id
                : 'ip:'.$request->ip();

            return Limit::perMinute($limit)->by($scope.'|'.$routeName);
        });
    }
}
