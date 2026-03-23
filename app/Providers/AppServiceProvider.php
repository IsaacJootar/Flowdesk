<?php

namespace App\Providers;

use App\Services\Execution\ExecutionAdapterRegistry;
use App\Services\Execution\TenantExecutionAdapterFactory;
use App\Services\Operations\ProductionReadinessValidator;
use App\Services\RequestCommunication\Sms\NullSmsProvider;
use App\Services\RequestCommunication\Sms\SmsProvider;
use App\Services\RequestCommunication\Sms\TermiiSmsProvider;
use App\Support\CorrelationContext;
use App\Support\FlowdeskLogContext;
use App\Support\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use RuntimeException;

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
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(CorrelationContext::class);
        $this->app->singleton(FlowdeskLogContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureObservability();
        $this->configureProductionGuardrails();

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

    private function configureObservability(): void
    {
        $correlationContext = $this->app->make(CorrelationContext::class);
        $flowdeskLogContext = $this->app->make(FlowdeskLogContext::class);

        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests() && ! $correlationContext->correlationId()) {
            $correlationContext->setCorrelationId((string) Str::uuid());
            $correlationContext->mergeContext([
                'console_command' => implode(' ', array_slice($_SERVER['argv'] ?? [], 1)),
            ]);
            $flowdeskLogContext->share($correlationContext->all());
        }

        Queue::createPayloadUsing(function () use ($correlationContext): array {
            return [
                'flowdesk_context' => $correlationContext->all(),
            ];
        });

        Queue::before(function (JobProcessing $event) use ($correlationContext, $flowdeskLogContext): void {
            $payload = $event->job->payload();
            $context = (array) ($payload['flowdesk_context'] ?? []);

            $correlationContext->clear();

            if (isset($context['correlation_id'])) {
                $correlationContext->setCorrelationId((string) $context['correlation_id']);
            }

            $correlationContext->mergeContext(array_merge($context, [
                'queue_connection' => $event->connectionName,
                'queue_name' => method_exists($event->job, 'getQueue') ? $event->job->getQueue() : null,
            ]));

            $flowdeskLogContext->share($correlationContext->all());
        });

        Queue::after(function (JobProcessed $event) use ($correlationContext): void {
            $correlationContext->clear();
        });

        Queue::failing(function (JobFailed $event) use ($correlationContext): void {
            $correlationContext->clear();
        });
    }

    private function configureProductionGuardrails(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        $issues = $this->app->make(ProductionReadinessValidator::class)->blockingConfigurationIssues();

        if ($issues === []) {
            return;
        }

        Log::critical('Flowdesk production guardrails detected blocking configuration issues.', [
            'issues' => $issues,
        ]);

        if ((bool) config('observability.production_validation.fail_fast', false)) {
            throw new RuntimeException('Flowdesk production validation failed. Review blocking configuration issues before boot.');
        }
    }
}
