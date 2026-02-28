<?php

namespace App\Services;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\TenantBillingLedgerEntry;
use App\Domains\Company\Models\TenantPlanChangeHistory;
use App\Domains\Company\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TenantBillingAutomationService
{
    public function __construct(
        private readonly TenantAuditLogger $tenantAuditLogger,
    ) {
    }

    /**
     * Evaluate all external tenant subscriptions and auto-update billing status.
     */
    public function evaluateAllExternal(?User $actor = null): int
    {
        $updated = 0;

        Company::query()
            ->whereNotIn('slug', $this->internalCompanySlugs())
            ->with('subscription')
            ->chunkById(100, function ($companies) use ($actor, &$updated): void {
                foreach ($companies as $company) {
                    if ($this->evaluateCompany($company, $actor)) {
                        $updated++;
                    }
                }
            });

        return $updated;
    }

    /**
     * Returns true when subscription status changed.
     */
    public function evaluateCompany(Company $company, ?User $actor = null): bool
    {
        /** @var TenantSubscription|null $subscription */
        $subscription = $company->subscription()->first();
        if (! $subscription) {
            return false;
        }

        $previousStatus = (string) $subscription->subscription_status;
        $newStatus = $this->deriveStatus($company, $subscription);

        if ($newStatus === $previousStatus) {
            return false;
        }

        $subscription->forceFill([
            'subscription_status' => $newStatus,
            'updated_by' => $actor?->id,
        ])->save();

        TenantPlanChangeHistory::query()->create([
            'company_id' => (int) $company->id,
            'tenant_subscription_id' => (int) $subscription->id,
            'previous_plan_code' => (string) $subscription->plan_code,
            'new_plan_code' => (string) $subscription->plan_code,
            'previous_subscription_status' => $previousStatus,
            'new_subscription_status' => $newStatus,
            'changed_at' => now(),
            'reason' => 'Automated billing status update from coverage + grace policy.',
            'changed_by' => $actor?->id,
        ]);

        $this->tenantAuditLogger->log(
            companyId: (int) $company->id,
            action: 'tenant.billing.status_automated',
            actor: $actor,
            description: 'Billing status updated by automation engine.',
            entityType: TenantSubscription::class,
            entityId: (int) $subscription->id,
            metadata: [
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
            ],
        );

        return true;
    }

    /**
     * If a payment period extends beyond current subscription end date, extend coverage.
     * This lets billing status automation derive state from real paid coverage windows.
     */
    public function syncCoverageFromPaymentPeriod(TenantSubscription $subscription, ?string $periodStart, ?string $periodEnd, ?User $actor = null): void
    {
        $periodStartValue = $this->nullableDate($periodStart);
        $periodEndValue = $this->nullableDate($periodEnd);

        if (! $periodEndValue) {
            return;
        }

        $currentEndsAt = $subscription->ends_at?->toDateString();
        $shouldExtend = $currentEndsAt === null || $periodEndValue > $currentEndsAt;

        if (! $shouldExtend) {
            return;
        }

        $subscription->forceFill([
            'starts_at' => $subscription->starts_at?->toDateString() ?: $periodStartValue,
            'ends_at' => $periodEndValue,
            'updated_by' => $actor?->id,
        ])->save();

        $this->tenantAuditLogger->log(
            companyId: (int) $subscription->company_id,
            action: 'tenant.billing.coverage_extended',
            actor: $actor,
            description: 'Subscription coverage extended from recorded payment period.',
            entityType: TenantSubscription::class,
            entityId: (int) $subscription->id,
            metadata: [
                'period_start' => $periodStartValue,
                'period_end' => $periodEndValue,
            ],
        );
    }

    private function deriveStatus(Company $company, TenantSubscription $subscription): string
    {
        if ((string) $company->lifecycle_status !== 'active') {
            return 'suspended';
        }

        $today = now()->startOfDay();
        $coverageEnd = $subscription->ends_at?->copy()?->startOfDay();

        if (! $coverageEnd) {
            return 'current';
        }

        if ($today->lessThanOrEqualTo($coverageEnd)) {
            return 'current';
        }

        $graceEnd = $subscription->grace_until?->copy()?->startOfDay()
            ?: $coverageEnd->copy()->addDays($this->defaultGraceDays());

        if ($today->lessThanOrEqualTo($graceEnd)) {
            return 'grace';
        }

        $daysOverdue = $graceEnd->diffInDays($today);
        if ($daysOverdue >= $this->autoSuspendDays()) {
            return 'suspended';
        }

        // If there is positive balance/credit after grace, keep overdue instead of immediate suspension.
        $balance = $this->ledgerBalance((int) $company->id);
        if ($balance > 0) {
            return 'overdue';
        }

        return 'overdue';
    }

    private function ledgerBalance(int $companyId): float
    {
        $credit = (float) TenantBillingLedgerEntry::query()
            ->where('company_id', $companyId)
            ->where('direction', 'credit')
            ->sum('amount');

        $debit = (float) TenantBillingLedgerEntry::query()
            ->where('company_id', $companyId)
            ->where('direction', 'debit')
            ->sum('amount');

        return round($credit - $debit, 2);
    }

    private function defaultGraceDays(): int
    {
        return max(0, (int) config('platform.billing_default_grace_days', 3));
    }

    private function autoSuspendDays(): int
    {
        return max(1, (int) config('platform.billing_auto_suspend_after_days_overdue', 14));
    }

    private function nullableDate(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? Carbon::parse($trimmed)->toDateString() : null;
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

