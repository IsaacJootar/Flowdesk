<?php

namespace App\Livewire\Settings;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Company\Models\TenantBillingAllocation;
use App\Domains\Company\Models\TenantBillingLedgerEntry;
use App\Domains\Company\Models\TenantManualPayment;
use App\Domains\Company\Models\TenantPlanChangeHistory;
use App\Domains\Company\Models\TenantSubscription;
use App\Domains\Company\Models\TenantUsageCounter;
use App\Services\PlatformAccessService;
use App\Services\TenantAuditLogger;
use App\Services\TenantBillingAutomationService;
use App\Services\TenantUsageSnapshotService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Layout('layouts.app')]
#[Title('Tenant Billing')]
class TenantDetailsPage extends Component
{
    use WithPagination;

    public Company $company;

    public bool $readyToLoad = false;

    public string $allocationStatusFilter = 'all';

    public int $ledgerPerPage = 10;

    public int $allocationPerPage = 10;

    public int $auditPerPage = 10;

    public bool $showPaymentModal = false;

    /** @var array{amount:string,currency_code:string,payment_method:string,reference:string,received_at:string,period_start:string,period_end:string,note:string} */
    public array $paymentForm = [];

    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    public function mount(Company $company): void
    {
        $this->authorizePlatformOperator();
        $this->assertTenantIsExternal($company);
        $this->company = $company;
        session(['platform_active_tenant_id' => (int) $company->id]);
        $this->paymentForm = [
            'amount' => '',
            'currency_code' => strtoupper((string) ($company->currency_code ?: 'NGN')),
            'payment_method' => 'offline_transfer',
            'reference' => '',
            'received_at' => $this->companyNowForInput(),
            'period_start' => '',
            'period_end' => '',
            'note' => '',
        ];
    }

    public function loadData(): void
    {
        if ($this->readyToLoad) {
            return;
        }

        $this->readyToLoad = true;
        app(TenantUsageSnapshotService::class)->capture((int) $this->company->id, auth()->user());
    }

    public function updatedAllocationStatusFilter(): void
    {
        if (! in_array($this->allocationStatusFilter, ['all', 'allocated', 'unapplied', 'reversed'], true)) {
            $this->allocationStatusFilter = 'all';
        }

        $this->resetPage('allocationPage');
    }

    public function openPaymentModal(): void
    {
        $this->authorizePlatformOperator();
        $this->showPaymentModal = true;
        $this->resetValidation();
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
        $this->paymentForm = [
            'amount' => '',
            'currency_code' => strtoupper((string) ($this->company->currency_code ?: 'NGN')),
            'payment_method' => 'offline_transfer',
            'reference' => '',
            'received_at' => $this->companyNowForInput(),
            'period_start' => '',
            'period_end' => '',
            'note' => '',
        ];
        $this->resetValidation();
    }

    public function saveManualPayment(): void
    {
        $this->authorizePlatformOperator();

        $this->validate([
            'paymentForm.amount' => ['required', 'numeric', 'min:0.01'],
            'paymentForm.currency_code' => ['required', 'string', 'size:3'],
            'paymentForm.payment_method' => ['required', Rule::in(['cash', 'offline_transfer', 'cheque', 'other'])],
            'paymentForm.reference' => ['nullable', 'string', 'max:100'],
            'paymentForm.received_at' => ['required', 'date'],
            'paymentForm.period_start' => ['nullable', 'date'],
            'paymentForm.period_end' => ['nullable', 'date'],
            'paymentForm.note' => ['nullable', 'string', 'max:1000'],
        ]);

        $actor = Auth::user();
        if (! $actor) {
            throw new AuthorizationException('User session is required.');
        }

        $company = $this->tenantCompaniesBaseQuery()->findOrFail((int) $this->company->id);

        try {
            $subscription = $company->subscription()->first();

            $receivedAt = Carbon::createFromFormat(
                'Y-m-d\TH:i',
                (string) $this->paymentForm['received_at'],
                $this->companyTimezone()
            )->utc();

            $payment = TenantManualPayment::query()->create([
                'company_id' => (int) $company->id,
                'tenant_subscription_id' => $subscription?->id,
                'amount' => (float) $this->paymentForm['amount'],
                'currency_code' => strtoupper((string) $this->paymentForm['currency_code']),
                'payment_method' => (string) $this->paymentForm['payment_method'],
                'reference' => $this->nullableString((string) $this->paymentForm['reference']),
                'received_at' => $receivedAt->toDateTimeString(),
                'period_start' => $this->normalizeDate((string) $this->paymentForm['period_start']),
                'period_end' => $this->normalizeDate((string) $this->paymentForm['period_end']),
                'note' => $this->nullableString((string) $this->paymentForm['note']),
                'recorded_by' => $actor->id,
            ]);

            $allocationStatus = $this->hasCoveragePeriod() ? 'allocated' : 'unapplied';

            TenantBillingAllocation::query()->create([
                'company_id' => (int) $company->id,
                'tenant_manual_payment_id' => $payment->id,
                'tenant_subscription_id' => $subscription?->id,
                'amount' => (float) $payment->amount,
                'currency_code' => (string) $payment->currency_code,
                'period_start' => $payment->period_start?->toDateString(),
                'period_end' => $payment->period_end?->toDateString(),
                'allocation_status' => $allocationStatus,
                'note' => $allocationStatus === 'unapplied'
                    ? 'No period selected yet. Requires allocation/reconciliation.'
                    : 'Payment allocated to selected period window.',
                'metadata' => [
                    'payment_method' => (string) $payment->payment_method,
                    'reference' => (string) ($payment->reference ?? ''),
                ],
                'created_by' => $actor->id,
            ]);

            TenantBillingLedgerEntry::query()->create([
                'company_id' => (int) $company->id,
                'tenant_subscription_id' => $subscription?->id,
                'source_type' => TenantManualPayment::class,
                'source_id' => $payment->id,
                'entry_type' => 'payment',
                'direction' => 'credit',
                'amount' => (float) $payment->amount,
                'currency_code' => (string) $payment->currency_code,
                'effective_date' => $receivedAt->toDateString(),
                'period_start' => $payment->period_start?->toDateString(),
                'period_end' => $payment->period_end?->toDateString(),
                'description' => 'Offline payment received',
                'metadata' => [
                    'payment_method' => (string) $payment->payment_method,
                    'reference' => (string) ($payment->reference ?? ''),
                    'allocation_status' => $allocationStatus,
                ],
                'created_by' => $actor->id,
            ]);

            app(TenantAuditLogger::class)->log(
                companyId: (int) $company->id,
                action: 'tenant.billing.payment_recorded',
                actor: $actor,
                description: 'Manual tenant payment recorded.',
                entityType: TenantManualPayment::class,
                entityId: (int) $payment->id,
                metadata: [
                    'amount' => (float) $payment->amount,
                    'currency' => (string) $payment->currency_code,
                    'allocation_status' => $allocationStatus,
                ],
            );

            if ($subscription) {
                $automationService = app(TenantBillingAutomationService::class);
                $automationService->syncCoverageFromPaymentPeriod(
                    subscription: $subscription,
                    periodStart: $payment->period_start?->toDateString(),
                    periodEnd: $payment->period_end?->toDateString(),
                    actor: $actor
                );

                $subscription->refresh();
                $automationService->evaluateCompany($company, $actor);
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->setFeedbackError('Unable to record manual payment right now.');

            return;
        }

        $this->setFeedback('Manual payment recorded.');
        $this->closePaymentModal();
    }

    public function render(): View
    {
        $this->authorizePlatformOperator();
        $this->assertTenantIsExternal($this->company);

        $subscription = $this->company->subscription()->first();
        $latestUsage = $this->company->usageCounters()->latest('snapshot_at')->first();

        $stats = [
            'balance' => $this->readyToLoad ? $this->billingBalance((int) $this->company->id) : 0.0,
            'unapplied' => $this->readyToLoad
                ? (float) TenantBillingAllocation::query()
                    ->where('company_id', (int) $this->company->id)
                    ->where('allocation_status', 'unapplied')
                    ->sum('amount')
                : 0.0,
            'seat_limit' => (int) ($subscription?->seat_limit ?? 0),
            'active_users' => $this->readyToLoad
                ? (int) $this->company->users()->where('is_active', true)->count()
                : 0,
            'seat_utilization' => (float) ($latestUsage?->seat_utilization_percent ?? 0),
            'warning_level' => (string) ($latestUsage?->warning_level ?? 'normal'),
        ];

        $ledgerEntries = $this->readyToLoad
            ? TenantBillingLedgerEntry::query()
                ->where('company_id', (int) $this->company->id)
                ->latest('effective_date')
                ->latest('id')
                ->paginate($this->ledgerPerPage, ['*'], 'ledgerPage')
            : $this->emptyPaginator($this->ledgerPerPage, 'ledgerPage');

        $allocations = $this->readyToLoad
            ? TenantBillingAllocation::query()
                ->with(['manualPayment:id,reference,payment_method,received_at', 'creator:id,name'])
                ->where('company_id', (int) $this->company->id)
                ->when(
                    $this->allocationStatusFilter !== 'all',
                    fn (Builder $query) => $query->where('allocation_status', $this->allocationStatusFilter)
                )
                ->latest('id')
                ->paginate($this->allocationPerPage, ['*'], 'allocationPage')
            : $this->emptyPaginator($this->allocationPerPage, 'allocationPage');

        $planHistory = $this->readyToLoad
            ? TenantPlanChangeHistory::query()
                ->with(['changer:id,name'])
                ->where('company_id', (int) $this->company->id)
                ->latest('changed_at')
                ->limit(20)
                ->get()
            : collect();

        $usageSnapshots = $this->readyToLoad
            ? TenantUsageCounter::query()
                ->where('company_id', (int) $this->company->id)
                ->latest('snapshot_at')
                ->limit(20)
                ->get()
            : collect();

        $auditEvents = $this->readyToLoad
            ? TenantAuditEvent::query()
                ->with(['actor:id,name'])
                ->where('company_id', (int) $this->company->id)
                ->latest('event_at')
                ->paginate($this->auditPerPage, ['*'], 'auditPage')
            : $this->emptyPaginator($this->auditPerPage, 'auditPage');

        return view('livewire.settings.tenant-details-page', [
            'subscription' => $subscription,
            'stats' => $stats,
            'ledgerEntries' => $ledgerEntries,
            'allocations' => $allocations,
            'planHistory' => $planHistory,
            'usageSnapshots' => $usageSnapshots,
            'auditEvents' => $auditEvents,
        ]);
    }

    private function billingBalance(int $companyId): float
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

    private function hasCoveragePeriod(): bool
    {
        return trim((string) $this->paymentForm['period_start']) !== ''
            && trim((string) $this->paymentForm['period_end']) !== '';
    }

    private function normalizeDate(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function nullableString(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function setFeedback(string $message): void
    {
        $this->feedbackError = null;
        $this->feedbackMessage = $message;
        $this->feedbackKey++;
    }

    private function setFeedbackError(string $message): void
    {
        $this->feedbackMessage = null;
        $this->feedbackError = $message;
        $this->feedbackKey++;
    }

    private function companyTimezone(): string
    {
        $timezone = trim((string) ($this->company->timezone ?? 'Africa/Lagos'));

        return $timezone !== '' ? $timezone : 'Africa/Lagos';
    }

    private function companyNowForInput(): string
    {
        return Carbon::now($this->companyTimezone())->format('Y-m-d\\TH:i');
    }

    public function companyTimezoneLabel(): string
    {
        return $this->companyTimezone();
    }

    public function formatInCompanyTimezone(mixed $value, string $format = 'M d, Y H:i'): string
    {
        if (! $value) {
            return '-';
        }

        try {
            return Carbon::parse($value)
                ->timezone($this->companyTimezone())
                ->format($format);
        } catch (Throwable) {
            return '-';
        }
    }

    private function emptyPaginator(int $perPage, string $pageName)
    {
        return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
            'pageName' => $pageName,
        ]);
    }

    private function authorizePlatformOperator(): void
    {
        app(PlatformAccessService::class)->authorizePlatformOperator();
    }

    private function assertTenantIsExternal(Company $company): void
    {
        $internalSlugs = array_values(array_unique(array_filter(array_map(
            static fn (mixed $slug): string => strtolower(trim((string) $slug)),
            (array) config('platform.internal_company_slugs', [])
        ))));

        if (in_array(strtolower((string) $company->slug), $internalSlugs, true)) {
            throw new AuthorizationException('Internal platform company is not managed from tenant details.');
        }
    }
}


