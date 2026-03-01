<?php

namespace App\Services\Execution;

use App\Domains\Company\Models\TenantSubscription;
use App\Domains\Company\Models\TenantSubscriptionBillingAttempt;
use App\Jobs\Execution\RunSubscriptionBillingAttemptJob;
use App\Models\User;
use App\Services\TenantExecutionModeService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionAutoBillingOrchestrator
{
    public function __construct(
        private readonly TenantExecutionModeService $modeService,
    ) {
    }

    /**
     * Queue one billing attempt per tenant subscription for the active monthly cycle.
     *
     * @return array{scanned:int,queued:int,already_exists:int,skipped_zero_amount:int,skipped_provider:int}
     */
    public function dispatchDueBilling(?int $companyId = null, ?User $actor = null, bool $queueJobs = true): array
    {
        $stats = [
            'scanned' => 0,
            'queued' => 0,
            'already_exists' => 0,
            'skipped_zero_amount' => 0,
            'skipped_provider' => 0,
        ];

        $cycleStart = CarbonImmutable::now()->startOfMonth();
        $cycleEnd = CarbonImmutable::now()->endOfMonth();
        $cycleKey = $cycleStart->format('Y-m');

        TenantSubscription::query()
            ->with('company')
            ->where('payment_execution_mode', TenantExecutionModeService::MODE_EXECUTION_ENABLED)
            ->when($companyId !== null, fn (Builder $query) => $query->where('company_id', $companyId))
            ->whereHas('company', function (Builder $query): void {
                $query->whereNotIn('slug', $this->internalCompanySlugs())
                    ->where('lifecycle_status', 'active');
            })
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use (&$stats, $cycleStart, $cycleEnd, $cycleKey, $actor, $queueJobs): void {
                foreach ($subscriptions as $subscription) {
                    $stats['scanned']++;

                    $provider = trim((string) $subscription->execution_provider);
                    if ($provider === '') {
                        $stats['skipped_provider']++;

                        continue;
                    }

                    $amount = $this->planAmount((string) $subscription->plan_code);
                    if ($amount <= 0) {
                        $stats['skipped_zero_amount']++;

                        continue;
                    }

                    $attempt = TenantSubscriptionBillingAttempt::query()->firstOrCreate(
                        [
                            'company_id' => (int) $subscription->company_id,
                            'tenant_subscription_id' => (int) $subscription->id,
                            'billing_cycle_key' => $cycleKey,
                        ],
                        [
                            'provider_key' => strtolower($provider),
                            'idempotency_key' => $this->idempotencyKey((int) $subscription->company_id, (int) $subscription->id, $cycleKey),
                            'attempt_status' => 'queued',
                            'amount' => $amount,
                            'currency_code' => strtoupper((string) ($subscription->company?->currency_code ?: config('execution.billing.default_currency', 'NGN'))),
                            'period_start' => $cycleStart->toDateString(),
                            'period_end' => $cycleEnd->toDateString(),
                            'queued_at' => now(),
                            'attempt_count' => 1,
                            'metadata' => [
                                'plan_code' => (string) $subscription->plan_code,
                            ],
                            'created_by' => $actor?->id,
                            'updated_by' => $actor?->id,
                        ]
                    );

                    if (! $attempt->wasRecentlyCreated) {
                        $stats['already_exists']++;

                        continue;
                    }

                    $stats['queued']++;

                    if ($queueJobs) {
                        RunSubscriptionBillingAttemptJob::dispatch((int) $attempt->id);
                    }
                }
            });

        return $stats;
    }

    private function planAmount(string $planCode): float
    {
        return round((float) config('execution.billing.plan_amounts.'.strtolower(trim($planCode)), 0), 2);
    }

    private function idempotencyKey(int $companyId, int $subscriptionId, string $cycleKey): string
    {
        return sprintf('tenant:%d:subscription:%d:cycle:%s', $companyId, $subscriptionId, $cycleKey);
    }

    /**
     * @return array<int, string>
     */
    private function internalCompanySlugs(): array
    {
        $slugs = (array) config('platform.internal_company_slugs', []);

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $slug): string => strtolower(trim((string) $slug)),
            $slugs
        ))));
    }
}