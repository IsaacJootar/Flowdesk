<?php

namespace App\Livewire\Platform;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\TenantFeatureEntitlement;
use App\Domains\Company\Models\TenantPlanChangeHistory;
use App\Domains\Company\Models\TenantSubscription;
use App\Livewire\Platform\Concerns\InteractsWithTenantCompanies;
use App\Services\TenantAuditLogger;
use App\Services\TenantPlanDefaultsService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
#[Title('Tenant Plan & Modules')]
class TenantPlanEntitlementsPage extends Component
{
    use InteractsWithTenantCompanies;

    public Company $company;

    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    /** @var array{plan_code:string,subscription_status:string,seat_limit:string,starts_at:string,ends_at:string,grace_until:string,billing_reference:string,notes:string} */
    public array $planForm = [];

    /** @var array{requests_enabled:bool,expenses_enabled:bool,vendors_enabled:bool,budgets_enabled:bool,assets_enabled:bool,reports_enabled:bool,communications_enabled:bool,ai_enabled:bool,fintech_enabled:bool} */
    public array $entitlementsForm = [];

    public function mount(Company $company): void
    {
        $this->authorizePlatformOperator();
        $this->assertTenantIsExternal($company);
        $this->company = $company;
        session(['platform_active_tenant_id' => (int) $company->id]);
        $this->hydrateForms();
    }

    public function applyPlanDefaults(): void
    {
        $this->authorizePlatformOperator();

        $defaultsService = app(TenantPlanDefaultsService::class);
        $this->entitlementsForm = array_merge(
            $this->entitlementsForm,
            $defaultsService->formEntitlementsForPlan((string) ($this->planForm['plan_code'] ?? 'pilot'))
        );

        $defaults = $defaultsService->defaultsForPlan((string) ($this->planForm['plan_code'] ?? 'pilot'));
        $this->planForm['seat_limit'] = $defaults['seat_limit'] !== null ? (string) $defaults['seat_limit'] : '';

        $this->setFeedback('Plan defaults applied.');
    }

    public function save(): void
    {
        $this->authorizePlatformOperator();

        $this->validate([
            'planForm.plan_code' => ['required', Rule::in(['pilot', 'growth', 'business', 'enterprise'])],
            'planForm.subscription_status' => ['required', Rule::in(['current', 'grace', 'overdue', 'suspended'])],
            'planForm.seat_limit' => ['nullable', 'integer', 'min:1'],
            'planForm.starts_at' => ['nullable', 'date'],
            'planForm.ends_at' => ['nullable', 'date'],
            'planForm.grace_until' => ['nullable', 'date'],
            'planForm.billing_reference' => ['nullable', 'string', 'max:100'],
            'planForm.notes' => ['nullable', 'string', 'max:1000'],
            'entitlementsForm.requests_enabled' => ['boolean'],
            'entitlementsForm.expenses_enabled' => ['boolean'],
            'entitlementsForm.vendors_enabled' => ['boolean'],
            'entitlementsForm.budgets_enabled' => ['boolean'],
            'entitlementsForm.assets_enabled' => ['boolean'],
            'entitlementsForm.reports_enabled' => ['boolean'],
            'entitlementsForm.communications_enabled' => ['boolean'],
            'entitlementsForm.ai_enabled' => ['boolean'],
            'entitlementsForm.fintech_enabled' => ['boolean'],
        ]);

        $actor = Auth::user();
        if (! $actor) {
            throw new AuthorizationException('User session is required.');
        }

        $company = $this->tenantCompaniesBaseQuery()->findOrFail((int) $this->company->id);
        $subscription = $company->subscription()->first();
        $entitlements = $company->featureEntitlements()->first();

        $beforePlan = (string) ($subscription?->plan_code ?? 'pilot');
        $beforeStatus = (string) ($subscription?->subscription_status ?? 'current');

        try {
            $savedSubscription = TenantSubscription::query()->updateOrCreate(
                ['company_id' => (int) $company->id],
                [
                    'plan_code' => (string) $this->planForm['plan_code'],
                    'subscription_status' => (string) $this->planForm['subscription_status'],
                    'starts_at' => $this->nullableDate($this->planForm['starts_at']),
                    'ends_at' => $this->nullableDate($this->planForm['ends_at']),
                    'grace_until' => $this->nullableDate($this->planForm['grace_until']),
                    'seat_limit' => $this->nullableInteger($this->planForm['seat_limit']),
                    'billing_reference' => $this->nullableString($this->planForm['billing_reference']),
                    'notes' => $this->nullableString($this->planForm['notes']),
                    'created_by' => $subscription?->created_by ?? $actor->id,
                    'updated_by' => $actor->id,
                ]
            );

            $savedEntitlements = TenantFeatureEntitlement::query()->updateOrCreate(
                ['company_id' => (int) $company->id],
                [
                    'requests_enabled' => (bool) $this->entitlementsForm['requests_enabled'],
                    'expenses_enabled' => (bool) $this->entitlementsForm['expenses_enabled'],
                    'vendors_enabled' => (bool) $this->entitlementsForm['vendors_enabled'],
                    'budgets_enabled' => (bool) $this->entitlementsForm['budgets_enabled'],
                    'assets_enabled' => (bool) $this->entitlementsForm['assets_enabled'],
                    'reports_enabled' => (bool) $this->entitlementsForm['reports_enabled'],
                    'communications_enabled' => (bool) $this->entitlementsForm['communications_enabled'],
                    'ai_enabled' => (bool) $this->entitlementsForm['ai_enabled'],
                    'fintech_enabled' => (bool) $this->entitlementsForm['fintech_enabled'],
                    'created_by' => $entitlements?->created_by ?? $actor->id,
                    'updated_by' => $actor->id,
                ]
            );

            if ($beforePlan !== (string) $savedSubscription->plan_code || $beforeStatus !== (string) $savedSubscription->subscription_status) {
                TenantPlanChangeHistory::query()->create([
                    'company_id' => (int) $company->id,
                    'tenant_subscription_id' => (int) $savedSubscription->id,
                    'previous_plan_code' => $beforePlan,
                    'new_plan_code' => (string) $savedSubscription->plan_code,
                    'previous_subscription_status' => $beforeStatus,
                    'new_subscription_status' => (string) $savedSubscription->subscription_status,
                    'changed_at' => now(),
                    'reason' => 'Updated from platform plan/modules page.',
                    'changed_by' => $actor->id,
                ]);
            }

            app(TenantAuditLogger::class)->log(
                companyId: (int) $company->id,
                action: 'tenant.plan_entitlements.updated',
                actor: $actor,
                description: 'Plan and module entitlements updated from dedicated tenant page.',
                entityType: TenantFeatureEntitlement::class,
                entityId: (int) $savedEntitlements->id,
                metadata: [
                    'plan_code' => (string) $savedSubscription->plan_code,
                    'subscription_status' => (string) $savedSubscription->subscription_status,
                ],
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->setFeedbackError('Unable to save plan and module settings right now.');

            return;
        }

        $this->hydrateForms();
        $this->setFeedback('Plan and module settings updated.');
    }

    public function render(): View
    {
        $this->authorizePlatformOperator();
        $this->assertTenantIsExternal($this->company);

        $company = $this->tenantCompaniesBaseQuery()->findOrFail((int) $this->company->id);

        return view('livewire.platform.tenant-plan-entitlements-page', [
            'company' => $company,
            'plans' => (array) config('tenant_plans.plans', []),
        ]);
    }

    private function hydrateForms(): void
    {
        $company = $this->tenantCompaniesBaseQuery()
            ->with(['subscription', 'featureEntitlements'])
            ->findOrFail((int) $this->company->id);

        $subscription = $company->subscription;
        $entitlements = $company->featureEntitlements;

        $this->planForm = [
            'plan_code' => (string) ($subscription?->plan_code ?? config('tenant_plans.default_plan', 'pilot')),
            'subscription_status' => (string) ($subscription?->subscription_status ?? 'current'),
            'seat_limit' => $subscription?->seat_limit !== null ? (string) $subscription->seat_limit : '',
            'starts_at' => (string) optional($subscription?->starts_at)->toDateString(),
            'ends_at' => (string) optional($subscription?->ends_at)->toDateString(),
            'grace_until' => (string) optional($subscription?->grace_until)->toDateString(),
            'billing_reference' => (string) ($subscription?->billing_reference ?? ''),
            'notes' => (string) ($subscription?->notes ?? ''),
        ];

        $this->entitlementsForm = [
            'requests_enabled' => (bool) ($entitlements?->requests_enabled ?? true),
            'expenses_enabled' => (bool) ($entitlements?->expenses_enabled ?? true),
            'vendors_enabled' => (bool) ($entitlements?->vendors_enabled ?? true),
            'budgets_enabled' => (bool) ($entitlements?->budgets_enabled ?? true),
            'assets_enabled' => (bool) ($entitlements?->assets_enabled ?? true),
            'reports_enabled' => (bool) ($entitlements?->reports_enabled ?? true),
            'communications_enabled' => (bool) ($entitlements?->communications_enabled ?? true),
            'ai_enabled' => (bool) ($entitlements?->ai_enabled ?? false),
            'fintech_enabled' => (bool) ($entitlements?->fintech_enabled ?? false),
        ];
    }

    private function nullableDate(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function nullableInteger(string $value): ?int
    {
        $trimmed = trim($value);

        return $trimmed !== '' ? (int) $trimmed : null;
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
}
