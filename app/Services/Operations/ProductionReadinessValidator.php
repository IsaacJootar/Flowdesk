<?php

namespace App\Services\Operations;

use App\Domains\Company\Models\TenantSubscription;
use App\Services\TenantExecutionModeService;
use Illuminate\Database\QueryException;

class ProductionReadinessValidator
{
    /**
     * @return array<int, array{severity:string,code:string,message:string}>
     */
    public function validate(): array
    {
        return array_values(array_merge(
            $this->configurationIssues(),
            $this->executionProviderIssues(),
        ));
    }

    /**
     * @return array{ok:bool,blocking:int,warning:int,issues:array<int, array{severity:string,code:string,message:string}>}
     */
    public function summary(): array
    {
        $issues = $this->validate();

        return [
            'ok' => $issues === [],
            'blocking' => count(array_filter($issues, static fn (array $issue): bool => $issue['severity'] === 'critical')),
            'warning' => count(array_filter($issues, static fn (array $issue): bool => $issue['severity'] === 'warning')),
            'issues' => $issues,
        ];
    }

    /**
     * Lightweight configuration-only checks used during application boot.
     *
     * @return array<int, array{severity:string,code:string,message:string}>
     */
    public function blockingConfigurationIssues(): array
    {
        return array_values(array_filter(
            $this->configurationIssues(),
            static fn (array $issue): bool => $issue['severity'] === 'critical'
        ));
    }

    /**
     * @return array<int, array{severity:string,code:string,message:string}>
     */
    private function configurationIssues(): array
    {
        $issues = [];

        if ($this->isProduction() && (bool) config('app.debug', false)) {
            $issues[] = $this->issue('critical', 'app_debug_enabled', 'APP_DEBUG must be disabled in production.');
        }

        if ($this->isProduction() && (string) config('queue.default', 'sync') === 'sync') {
            $issues[] = $this->issue('critical', 'queue_sync_driver', 'QUEUE_CONNECTION=sync is not safe for production automation workloads.');
        }

        if ($this->isProduction() && (string) config('cache.default', 'array') === 'array') {
            $issues[] = $this->issue('critical', 'cache_array_driver', 'CACHE_STORE=array disables shared cache state needed for production scheduling and runtime health.');
        }

        if ($this->isProduction() && in_array((string) config('mail.default', 'log'), ['log', 'array'], true)) {
            $issues[] = $this->issue('critical', 'mail_log_driver', 'MAIL_MAILER must point to a real delivery transport in production.');
        }

        if ($this->isProduction() && ! (bool) config('session.secure', false)) {
            $issues[] = $this->issue('warning', 'session_secure_cookie_disabled', 'SESSION_SECURE_COOKIE should be enabled for HTTPS production deployments.');
        }

        if ($this->isProduction() && (string) config('services.sms.provider', 'placeholder') === 'placeholder') {
            $issues[] = $this->issue('critical', 'sms_placeholder_provider', 'SMS provider is still set to placeholder for production.');
        }

        if ($this->isProduction() && in_array((string) config('execution.fallback_provider', 'null'), ['null', 'manual_ops'], true)) {
            $issues[] = $this->issue('warning', 'execution_fallback_manual', 'Execution fallback provider is still null/manual_ops. Keep this only for controlled incidents.');
        }

        return $issues;
    }

    /**
     * @return array<int, array{severity:string,code:string,message:string}>
     */
    private function executionProviderIssues(): array
    {
        try {
            $providers = TenantSubscription::query()
                ->where('payment_execution_mode', TenantExecutionModeService::MODE_EXECUTION_ENABLED)
                ->pluck('execution_provider')
                ->filter()
                ->map(static fn (mixed $provider): string => strtolower(trim((string) $provider)))
                ->unique()
                ->values()
                ->all();
        } catch (QueryException) {
            return [
                $this->issue('warning', 'execution_provider_validation_unavailable', 'Execution provider validation could not run because required tables are not ready in this environment.'),
            ];
        }

        $issues = [];

        foreach ($providers as $provider) {
            if (in_array($provider, ['null', 'manual_ops'], true)) {
                $issues[] = $this->issue('critical', 'execution_provider_'.$provider, 'Execution-enabled tenants still rely on the '.$provider.' provider.');

                continue;
            }

            if ($provider === 'paystack') {
                if (trim((string) config('execution.providers.paystack.secret_key', '')) === '') {
                    $issues[] = $this->issue('critical', 'paystack_secret_missing', 'Paystack secret key is missing for execution-enabled tenants.');
                }

                if (trim((string) config('execution.providers.paystack.webhook_secret', '')) === '') {
                    $issues[] = $this->issue('critical', 'paystack_webhook_secret_missing', 'Paystack webhook secret is missing for execution-enabled tenants.');
                }
            }

            if ($provider === 'flutterwave') {
                if (trim((string) config('execution.providers.flutterwave.secret_key', '')) === '') {
                    $issues[] = $this->issue('critical', 'flutterwave_secret_missing', 'Flutterwave secret key is missing for execution-enabled tenants.');
                }

                if (trim((string) config('execution.providers.flutterwave.webhook_secret_hash', '')) === '') {
                    $issues[] = $this->issue('critical', 'flutterwave_webhook_secret_missing', 'Flutterwave webhook secret hash is missing for execution-enabled tenants.');
                }
            }
        }

        return $issues;
    }

    /**
     * @return array{severity:string,code:string,message:string}
     */
    private function issue(string $severity, string $code, string $message): array
    {
        return [
            'severity' => $severity,
            'code' => $code,
            'message' => $message,
        ];
    }

    private function isProduction(): bool
    {
        return strtolower((string) config('app.env', app()->environment())) === 'production';
    }
}
