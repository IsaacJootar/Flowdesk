<?php

namespace App\Livewire\Platform;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\TenantSubscription;
use App\Livewire\Platform\Concerns\InteractsWithTenantCompanies;
use App\Services\TenantAuditLogger;
use App\Services\TenantExecutionModeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
#[Title('Client Payment Controls')]
class TenantExecutionPolicyPage extends Component
{
    use InteractsWithTenantCompanies;

    public Company $company;

    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    /** @var array{execution_max_transaction_amount:string,execution_daily_cap_amount:string,execution_monthly_cap_amount:string,execution_maker_checker_threshold_amount:string,execution_allowed_channels:array<int,string>,execution_policy_notes:string} */
    public array $policyForm = [];

    public function mount(Company $company): void
    {
        $this->authorizePlatformOperator();
        $this->assertTenantIsExternal($company);
        $this->company = $company;
        session(['platform_active_tenant_id' => (int) $company->id]);
        $this->hydrateForm();
    }

    public function save(): void
    {
        $this->authorizePlatformOperator();

        $service = app(TenantExecutionModeService::class);

        $this->validate([
            'policyForm.execution_max_transaction_amount' => ['nullable', 'numeric', 'min:0.01'],
            'policyForm.execution_daily_cap_amount' => ['nullable', 'numeric', 'min:0.01'],
            'policyForm.execution_monthly_cap_amount' => ['nullable', 'numeric', 'min:0.01'],
            'policyForm.execution_maker_checker_threshold_amount' => ['nullable', 'numeric', 'min:0.01'],
            'policyForm.execution_allowed_channels' => ['array'],
            'policyForm.execution_allowed_channels.*' => ['string', Rule::in($service->supportedChannels())],
            'policyForm.execution_policy_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $actor = Auth::user();
        if (! $actor) {
            throw new AuthorizationException('User session is required.');
        }

        $company = $this->tenantCompaniesBaseQuery()
            ->with(['subscription', 'featureEntitlements'])
            ->findOrFail((int) $this->company->id);

        $subscription = $company->subscription;
        $entitlements = $company->featureEntitlements;

        try {
            $normalized = $service->normalizeForSave(
                lifecycleStatus: (string) ($company->lifecycle_status ?? 'inactive'),
                subscriptionStatus: (string) ($subscription?->subscription_status ?? 'current'),
                entitlements: [
                    'requests_enabled' => (bool) ($entitlements?->requests_enabled ?? true),
                    'expenses_enabled' => (bool) ($entitlements?->expenses_enabled ?? true),
                ],
                paymentExecutionMode: (string) ($subscription?->payment_execution_mode ?? TenantExecutionModeService::MODE_DECISION_ONLY),
                provider: (string) ($subscription?->execution_provider ?? ''),
                allowedChannels: $this->policyForm['execution_allowed_channels'] ?? [],
                maxTransaction: $this->policyForm['execution_max_transaction_amount'] ?? null,
                dailyCap: $this->policyForm['execution_daily_cap_amount'] ?? null,
                monthlyCap: $this->policyForm['execution_monthly_cap_amount'] ?? null,
                makerCheckerThreshold: $this->policyForm['execution_maker_checker_threshold_amount'] ?? null,
                policyNotes: (string) ($this->policyForm['execution_policy_notes'] ?? ''),
                companyId: (int) $company->id,
            );

            $saved = TenantSubscription::query()->updateOrCreate(
                ['company_id' => (int) $company->id],
                [
                    'plan_code' => (string) ($subscription?->plan_code ?? config('tenant_plans.default_plan', 'pilot')),
                    'subscription_status' => (string) ($subscription?->subscription_status ?? 'current'),
                    'payment_execution_mode' => (string) ($subscription?->payment_execution_mode ?? TenantExecutionModeService::MODE_DECISION_ONLY),
                    'execution_provider' => (string) ($subscription?->execution_provider ?? ''),
                    'execution_max_transaction_amount' => $normalized['execution_max_transaction_amount'],
                    'execution_daily_cap_amount' => $normalized['execution_daily_cap_amount'],
                    'execution_monthly_cap_amount' => $normalized['execution_monthly_cap_amount'],
                    'execution_maker_checker_threshold_amount' => $normalized['execution_maker_checker_threshold_amount'], // dont go beyound this amount, no matter what. if transaction amount is above this, maker checker will be enforced regardless of channel or provider settings.
                    'execution_allowed_channels' => $normalized['execution_allowed_channels'],
                    'execution_policy_notes' => $normalized['execution_policy_notes'],
                    'created_by' => $subscription?->created_by ?? $actor->id,
                    'updated_by' => $actor->id,
                ]
            );

            app(TenantAuditLogger::class)->log(
                companyId: (int) $company->id,
                action: 'tenant.execution_policy.updated',
                actor: $actor,
                description: 'Execution policy updated from dedicated tenant page.',
                entityType: TenantSubscription::class,
                entityId: (int) $saved->id,
                metadata: [
                    'channels' => (array) ($saved->execution_allowed_channels ?? []),
                    'checker_threshold' => $saved->execution_maker_checker_threshold_amount,
                ],
            );
        } catch (ValidationException $exception) {
            $this->setFeedbackError((string) collect($exception->errors())->flatten()->first());

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->setFeedbackError('Unable to update execution policy right now.');

            return;
        }

        $this->hydrateForm();
        $this->setFeedback('Execution policy updated.');
    }

    public function render(): View
    {
        $this->authorizePlatformOperator();
        $this->assertTenantIsExternal($this->company);

        $company = $this->tenantCompaniesBaseQuery()->findOrFail((int) $this->company->id);

        return view('livewire.platform.tenant-execution-policy-page', [
            'company' => $company,
            'channels' => app(TenantExecutionModeService::class)->supportedChannels(),
            'futureChannels' => app(TenantExecutionModeService::class)->futureChannels(),
        ]);
    }

    private function hydrateForm(): void
    {
        $company = $this->tenantCompaniesBaseQuery()
            ->with('subscription')
            ->findOrFail((int) $this->company->id);

        $subscription = $company->subscription;

        $service = app(TenantExecutionModeService::class);
        $activeChannels = $service->supportedChannels();
        $savedChannels = array_values(array_filter(
            array_map('strval', (array) ($subscription?->execution_allowed_channels ?? [])),
            static fn (string $channel): bool => in_array($channel, $activeChannels, true)
        ));

        $this->policyForm = [
            'execution_max_transaction_amount' => $subscription?->execution_max_transaction_amount !== null
                ? (string) $subscription->execution_max_transaction_amount
                : '',
            'execution_daily_cap_amount' => $subscription?->execution_daily_cap_amount !== null
                ? (string) $subscription->execution_daily_cap_amount
                : '',
            'execution_monthly_cap_amount' => $subscription?->execution_monthly_cap_amount !== null
                ? (string) $subscription->execution_monthly_cap_amount
                : '',
            'execution_maker_checker_threshold_amount' => $subscription?->execution_maker_checker_threshold_amount !== null
                ? (string) $subscription->execution_maker_checker_threshold_amount
                : '',
            'execution_allowed_channels' => $savedChannels,
            'execution_policy_notes' => (string) ($subscription?->execution_policy_notes ?? ''),
        ];
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
}
