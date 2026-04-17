<?php

namespace App\Services\PaymentsRails;

use App\Domains\Fintech\Models\CompanyPaymentRailSetting;
use App\Services\Execution\ExecutionAdapterRegistry;
use Illuminate\Support\Facades\Http;
use Throwable;

class PaymentsRailSettingsService
{
    public function __construct(
        private readonly ExecutionAdapterRegistry $executionAdapterRegistry,
    ) {
    }

    public function settingsForCompany(int $companyId): CompanyPaymentRailSetting
    {
        return CompanyPaymentRailSetting::query()->firstOrCreate(
            ['company_id' => $companyId],
            CompanyPaymentRailSetting::defaultAttributes(),
        );
    }

    /**
     * @return array<int, string>
     */
    public function providerOptions(): array
    {
        $configured = $this->executionAdapterRegistry->providerKeys();

        $preferred = ['paystack', 'flutterwave', 'manual_ops'];

        $all = array_values(array_unique(array_filter(array_map(
            static fn (mixed $key): string => strtolower(trim((string) $key)),
            array_merge($preferred, $configured)
        ), static fn (string $key): bool => $key !== '' && $key !== 'null')));

        usort($all, static function (string $left, string $right) use ($preferred): int {
            $leftIndex = array_search($left, $preferred, true);
            $rightIndex = array_search($right, $preferred, true);

            if ($leftIndex !== false && $rightIndex !== false) {
                return $leftIndex <=> $rightIndex;
            }

            if ($leftIndex !== false) {
                return -1;
            }

            if ($rightIndex !== false) {
                return 1;
            }

            return strcmp($left, $right);
        });

        return $all;
    }

    /**
     * Run connection-time diagnostics before persisting external provider state.
     *
     * @return array{passed:bool,message:string,health_status:string,webhook_status:string,details:array<string,mixed>}
     */
    public function connectProvider(string $providerKey, bool $sandboxMode = false): array
    {
        return $this->runDiagnostics($providerKey, $sandboxMode, 'connect');
    }

    /**
     * Tenant-facing readiness check for the selected payment provider.
     *
     * @return array{passed:bool,message:string,health_status:string,webhook_status:string,details:array<string,mixed>}
     */
    public function testProvider(string $providerKey, bool $sandboxMode = false): array
    {
        return $this->runDiagnostics($providerKey, $sandboxMode, 'test');
    }

    /**
     * Trigger a manual provider sync probe and return health detail for UI/audit.
     *
     * @return array{passed:bool,message:string,health_status:string,webhook_status:string,details:array<string,mixed>}
     */
    public function syncProvider(string $providerKey, bool $sandboxMode = false): array
    {
        return $this->runDiagnostics($providerKey, $sandboxMode, 'sync');
    }

    /**
     * @return array{passed:bool,message:string,health_status:string,webhook_status:string,details:array<string,mixed>}
     */
    private function runDiagnostics(string $providerKey, bool $sandboxMode, string $context): array
    {
        $provider = strtolower(trim($providerKey));

        if ($provider === '') {
            return [
                'passed' => false,
                'message' => 'Select a provider before continuing.',
                'health_status' => 'action_needed',
                'webhook_status' => 'missing',
                'details' => ['provider' => ''],
            ];
        }

        if ($provider === 'manual_ops') {
            return [
                'passed' => true,
                'message' => $this->manualOpsContextMessage($context),
                'health_status' => 'healthy',
                'webhook_status' => 'optional',
                'details' => [
                    'provider' => 'manual_ops',
                    'sandbox_mode' => false,
                    'context' => $context,
                    'probe' => 'manual_no_external_call',
                ],
            ];
        }

        $credentials = $this->credentialsForProvider($provider, $sandboxMode);

        if ($credentials['secret_key'] === '') {
            return [
                'passed' => false,
                'message' => sprintf('%s %s credentials are not configured yet.', $this->providerDisplayName($provider), $sandboxMode ? 'sandbox' : 'live'),
                'health_status' => 'action_needed',
                'webhook_status' => 'missing',
                'details' => [
                    'provider' => $provider,
                    'sandbox_mode' => $sandboxMode,
                    'context' => $context,
                    'reason' => 'secret_key_missing',
                ],
            ];
        }

        $webhook = $this->webhookReadiness($provider, $sandboxMode, $credentials['secret_key']);

        if (! $webhook['ready']) {
            return [
                'passed' => false,
                'message' => (string) $webhook['message'],
                'health_status' => 'action_needed',
                'webhook_status' => (string) $webhook['status'],
                'details' => [
                    'provider' => $provider,
                    'sandbox_mode' => $sandboxMode,
                    'context' => $context,
                    'reason' => 'webhook_signature_not_ready',
                ],
            ];
        }

        $probe = $this->probeProvider($provider, $credentials['base_url'], $credentials['secret_key'], $sandboxMode);

        if (! $probe['passed']) {
            return [
                'passed' => false,
                'message' => sprintf('%s check failed: %s', $this->providerDisplayName($provider), (string) $probe['message']),
                'health_status' => 'degraded',
                'webhook_status' => (string) $webhook['status'],
                'details' => [
                    'provider' => $provider,
                    'sandbox_mode' => $sandboxMode,
                    'context' => $context,
                    'probe' => $probe,
                ],
            ];
        }

        return [
            'passed' => true,
            'message' => $this->successContextMessage($provider, $sandboxMode, $context),
            'health_status' => 'healthy',
            'webhook_status' => (string) $webhook['status'],
            'details' => [
                'provider' => $provider,
                'sandbox_mode' => $sandboxMode,
                'context' => $context,
                'probe' => $probe,
            ],
        ];
    }

    /**
     * @return array{secret_key:string,base_url:string}
     */
    private function credentialsForProvider(string $provider, bool $sandboxMode): array
    {
        $config = (array) config('execution.providers.'.$provider, []);

        $baseUrl = $sandboxMode
            ? trim((string) ($config['sandbox_base_url'] ?? $config['base_url'] ?? ''))
            : trim((string) ($config['base_url'] ?? ''));

        $secret = $sandboxMode
            ? trim((string) ($config['sandbox_secret_key'] ?? ''))
            : trim((string) ($config['secret_key'] ?? ''));

        if ($secret === '' && $sandboxMode) {
            // Fallback keeps pilot checks usable in environments with a single shared test key.
            $secret = trim((string) ($config['secret_key'] ?? ''));
        }

        return [
            'secret_key' => $secret,
            'base_url' => rtrim($baseUrl, '/'),
        ];
    }

    /**
     * @return array{ready:bool,status:string,message:string}
     */
    private function webhookReadiness(string $provider, bool $sandboxMode, string $secretKey): array
    {
        if ($provider === 'paystack') {
            $configuredSecret = trim((string) config(
                'execution.providers.paystack.'.($sandboxMode ? 'sandbox_webhook_secret' : 'webhook_secret'),
                ''
            ));
            $signingSecret = $configuredSecret !== '' ? $configuredSecret : $secretKey;

            if ($signingSecret === '') {
                return [
                    'ready' => false,
                    'status' => 'missing',
                    'message' => 'Webhook signing secret is missing for Paystack. Set webhook secret before connecting.',
                ];
            }

            return [
                'ready' => true,
                'status' => 'ready',
                'message' => 'Webhook signing is configured.',
            ];
        }

        if ($provider === 'flutterwave') {
            $hash = trim((string) config(
                'execution.providers.flutterwave.'.($sandboxMode ? 'sandbox_webhook_secret_hash' : 'webhook_secret_hash'),
                ''
            ));

            if ($hash !== '' || $secretKey !== '') {
                return [
                    'ready' => true,
                    'status' => 'ready',
                    'message' => 'Webhook signature verification is configured.',
                ];
            }

            return [
                'ready' => false,
                'status' => 'missing',
                'message' => 'Webhook verification hash or signing secret is required for Flutterwave.',
            ];
        }

        return [
            'ready' => true,
            'status' => 'unknown',
            'message' => 'Webhook validation readiness is not defined for this provider.',
        ];
    }

    /**
     * @return array{passed:bool,message:string,http_status:int|null,provider_status:string}
     */
    private function probeProvider(string $provider, string $baseUrl, string $secretKey, bool $sandboxMode): array
    {
        if ($baseUrl === '') {
            return [
                'passed' => false,
                'message' => 'Provider base URL is not configured.',
                'http_status' => null,
                'provider_status' => 'not_configured',
            ];
        }

        [$path, $successResolver] = match ($provider) {
            'paystack' => ['/balance', static fn (array $json): bool => (bool) ($json['status'] ?? false)],
            'flutterwave' => ['/balances', static fn (array $json): bool => in_array(strtolower((string) ($json['status'] ?? '')), ['success', 'successful'], true)],
            default => ['', static fn (array $json): bool => false],
        };

        if ($path === '') {
            return [
                'passed' => false,
                'message' => 'Unsupported provider probe path.',
                'http_status' => null,
                'provider_status' => 'unsupported',
            ];
        }

        try {
            $response = Http::timeout(20)
                ->withToken($secretKey)
                ->acceptJson()
                ->get($baseUrl.$path);

            $json = $response->json();
            if (! is_array($json)) {
                $json = [];
            }

            $passed = $response->successful() && $successResolver($json);

            return [
                'passed' => $passed,
                'message' => $passed
                    ? sprintf('%s %s probe succeeded.', $this->providerDisplayName($provider), $sandboxMode ? 'sandbox' : 'live')
                    : (string) ($json['message'] ?? 'Provider endpoint did not return a success response.'),
                'http_status' => $response->status(),
                'provider_status' => strtolower(trim((string) ($json['status'] ?? ''))),
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'passed' => false,
                'message' => 'Provider endpoint is unreachable. Check network, base URL, and credentials, then retry.',
                'http_status' => null,
                'provider_status' => 'exception',
            ];
        }
    }

    private function providerDisplayName(string $provider): string
    {
        return match ($provider) {
            'paystack' => 'Paystack',
            'flutterwave' => 'Flutterwave',
            'manual_ops' => 'Manual operations',
            default => ucfirst(str_replace('_', ' ', $provider)),
        };
    }

    private function manualOpsContextMessage(string $context): string
    {
        return match ($context) {
            'connect' => 'Payment provider connected (manual operations mode).',
            'sync' => 'Sync completed.',
            default => 'Connection test completed (manual operations mode).',
        };
    }

    private function successContextMessage(string $provider, bool $sandboxMode, string $context): string
    {
        $providerName = $this->providerDisplayName($provider);
        $mode = $sandboxMode ? 'sandbox' : 'live';

        return match ($context) {
            'connect' => sprintf('%s %s connection is ready.', $providerName, $mode),
            'sync' => sprintf('%s %s sync completed.', $providerName, $mode),
            default => sprintf('%s %s connection test passed.', $providerName, $mode),
        };
    }
}
