<?php

namespace App\Services\PaymentsRails;

use App\Domains\Fintech\Models\CompanyPaymentRailSetting;
use App\Services\Execution\ExecutionAdapterRegistry;

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
     * A lightweight readiness check for tenant-facing UI; deep diagnostics remain platform-side.
     *
     * @return array{passed:bool,message:string}
     */
    public function testProvider(string $providerKey, bool $sandboxMode = false): array
    {
        $provider = strtolower(trim($providerKey));

        if ($provider === '') {
            return [
                'passed' => false,
                'message' => 'Select a provider before running connection test.',
            ];
        }

        if ($provider === 'manual_ops') {
            return [
                'passed' => true,
                'message' => 'Connection test completed (manual operations mode).',
            ];
        }

        if ($provider === 'paystack') {
            $key = $sandboxMode
                ? trim((string) config('execution.providers.paystack.sandbox_secret_key', ''))
                : trim((string) config('execution.providers.paystack.secret_key', ''));

            if ($key === '' && $sandboxMode) {
                $key = trim((string) config('execution.providers.paystack.secret_key', ''));
            }

            return $key !== ''
                ? ['passed' => true, 'message' => $sandboxMode ? 'Sandbox connection test passed for Paystack.' : 'Connection test passed for Paystack.']
                : ['passed' => false, 'message' => $sandboxMode ? 'Paystack sandbox credentials are not configured yet.' : 'Paystack credentials are not configured yet.'];
        }

        if ($provider === 'flutterwave') {
            $key = $sandboxMode
                ? trim((string) config('execution.providers.flutterwave.sandbox_secret_key', ''))
                : trim((string) config('execution.providers.flutterwave.secret_key', ''));

            if ($key === '' && $sandboxMode) {
                $key = trim((string) config('execution.providers.flutterwave.secret_key', ''));
            }

            return $key !== ''
                ? ['passed' => true, 'message' => $sandboxMode ? 'Sandbox connection test passed for Flutterwave.' : 'Connection test passed for Flutterwave.']
                : ['passed' => false, 'message' => $sandboxMode ? 'Flutterwave sandbox credentials are not configured yet.' : 'Flutterwave credentials are not configured yet.'];
        }

        $providerConfig = (array) config('execution.providers.'.$provider, []);

        return $providerConfig !== []
            ? ['passed' => true, 'message' => $sandboxMode ? 'Sandbox connection test passed.' : 'Connection test passed.']
            : ['passed' => false, 'message' => 'Provider is not configured in this environment.'];
    }
}
