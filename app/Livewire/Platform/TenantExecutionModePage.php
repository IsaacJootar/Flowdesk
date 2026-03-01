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
#[Title('Tenant Execution Mode')]
class TenantExecutionModePage extends Component
{
    use InteractsWithTenantCompanies;

    public Company $company;

    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    /** @var array{payment_execution_mode:string,execution_provider:string} */
    public array $modeForm = [];

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
            'modeForm.payment_execution_mode' => ['required', Rule::in($service->supportedModes())],
            'modeForm.execution_provider' => ['nullable', 'string', 'max:80'],
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

        $previousMode = (string) ($subscription?->payment_execution_mode ?? TenantExecutionModeService::MODE_DECISION_ONLY);

        try {
            $normalized = $service->normalizeForSave(
                lifecycleStatus: (string) ($company->lifecycle_status ?? 'inactive'),
                subscriptionStatus: (string) ($subscription?->subscription_status ?? 'current'),
                entitlements: [
                    'requests_enabled' => (bool) ($entitlements?->requests_enabled ?? true),
                    'expenses_enabled' => (bool) ($entitlements?->expenses_enabled ?? true),
                ],
                paymentExecutionMode: (string) $this->modeForm['payment_execution_mode'],
                provider: (string) ($this->modeForm['execution_provider'] ?? ''),
                allowedChannels: (array) ($subscription?->execution_allowed_channels ?? []),
                maxTransaction: $subscription?->execution_max_transaction_amount,
                dailyCap: $subscription?->execution_daily_cap_amount,
                monthlyCap: $subscription?->execution_monthly_cap_amount,
                makerCheckerThreshold: $subscription?->execution_maker_checker_threshold_amount,
                policyNotes: (string) ($subscription?->execution_policy_notes ?? ''),
                companyId: (int) $company->id,
            );

            $isEnablingExecution = $previousMode !== TenantExecutionModeService::MODE_EXECUTION_ENABLED
                && $normalized['payment_execution_mode'] === TenantExecutionModeService::MODE_EXECUTION_ENABLED;
            $isDisablingExecution = $previousMode === TenantExecutionModeService::MODE_EXECUTION_ENABLED
                && $normalized['payment_execution_mode'] !== TenantExecutionModeService::MODE_EXECUTION_ENABLED;

            $saved = TenantSubscription::query()->updateOrCreate(
                ['company_id' => (int) $company->id],
                [
                    'plan_code' => (string) ($subscription?->plan_code ?? config('tenant_plans.default_plan', 'pilot')),
                    'subscription_status' => (string) ($subscription?->subscription_status ?? 'current'),
                    'payment_execution_mode' => (string) $normalized['payment_execution_mode'],
                    'execution_provider' => $normalized['execution_provider'],
                    'execution_enabled_at' => $isEnablingExecution
                        ? now()
                        : ($isDisablingExecution ? null : $subscription?->execution_enabled_at),
                    'execution_enabled_by' => $isEnablingExecution
                        ? $actor->id
                        : ($isDisablingExecution ? null : $subscription?->execution_enabled_by),
                    'execution_max_transaction_amount' => $normalized['execution_max_transaction_amount'],
                    'execution_daily_cap_amount' => $normalized['execution_daily_cap_amount'],
                    'execution_monthly_cap_amount' => $normalized['execution_monthly_cap_amount'],
                    'execution_maker_checker_threshold_amount' => $normalized['execution_maker_checker_threshold_amount'],
                    'execution_allowed_channels' => $normalized['execution_allowed_channels'],
                    'execution_policy_notes' => $normalized['execution_policy_notes'],
                    'created_by' => $subscription?->created_by ?? $actor->id,
                    'updated_by' => $actor->id,
                ]
            );

            app(TenantAuditLogger::class)->log(
                companyId: (int) $company->id,
                action: 'tenant.execution_mode.updated',
                actor: $actor,
                description: 'Execution mode updated from dedicated tenant page.',
                entityType: TenantSubscription::class,
                entityId: (int) $saved->id,
                metadata: [
                    'from' => $previousMode,
                    'to' => (string) $saved->payment_execution_mode,
                    'provider' => (string) ($saved->execution_provider ?? ''),
                ],
            );
        } catch (ValidationException $exception) {
            $this->setFeedbackError((string) collect($exception->errors())->flatten()->first());

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->setFeedbackError('Unable to update execution mode right now.');

            return;
        }

        $this->hydrateForm();
        $this->setFeedback('Execution mode updated.');
    }

    public function render(): View
    {
        $this->authorizePlatformOperator();
        $this->assertTenantIsExternal($this->company);

        $company = $this->tenantCompaniesBaseQuery()->findOrFail((int) $this->company->id);

        return view('livewire.platform.tenant-execution-mode-page', [
            'company' => $company,
            'modes' => app(TenantExecutionModeService::class)->supportedModes(),
        ]);
    }

    private function hydrateForm(): void
    {
        $company = $this->tenantCompaniesBaseQuery()
            ->with('subscription')
            ->findOrFail((int) $this->company->id);

        $subscription = $company->subscription;

        $this->modeForm = [
            'payment_execution_mode' => (string) ($subscription?->payment_execution_mode ?? TenantExecutionModeService::MODE_DECISION_ONLY),
            'execution_provider' => (string) ($subscription?->execution_provider ?? ''),
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
