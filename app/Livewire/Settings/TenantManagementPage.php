<?php

namespace App\Livewire\Settings;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Company\Models\TenantBillingAllocation;
use App\Domains\Company\Models\TenantBillingLedgerEntry;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\TenantFeatureEntitlement;
use App\Domains\Company\Models\TenantManualPayment;
use App\Domains\Company\Models\TenantPlanChangeHistory;
use App\Domains\Company\Models\TenantSubscription;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\PlatformAccessService;
use App\Services\TenantAuditLogger;
use App\Services\TenantBillingAutomationService;
use App\Services\TenantPlanDefaultsService;
use App\Services\TenantExecutionModeService;
use App\Services\TenantUsageSnapshotService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use DateTimeZone;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Layout('layouts.app')]
#[Title('Company Settings')]
class TenantManagementPage extends Component
{
    use WithPagination;

    public bool $readyToLoad = false;

    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $planFilter = 'all';

    public string $billingFilter = 'all';

    public int $perPage = 10;

    public bool $showTenantModal = false;

    public bool $isEditingTenant = false;

    public ?int $editingCompanyId = null;

    public bool $showPaymentModal = false;

    public ?int $paymentCompanyId = null;

    public ?string $originalPlanCode = null;

    public ?string $originalSubscriptionStatus = null;

    /** @var array{name:string,slug:string,email:string,phone:string,industry:string,currency_code:string,timezone:string,address:string,lifecycle_status:string} */
    public array $tenantForm = [];

    /** @var array{plan_code:string,subscription_status:string,payment_execution_mode:string,execution_provider:string,execution_max_transaction_amount:string,execution_daily_cap_amount:string,execution_monthly_cap_amount:string,execution_maker_checker_threshold_amount:string,execution_allowed_channels:array<int,string>,execution_policy_notes:string,starts_at:string,ends_at:string,grace_until:string,seat_limit:string,billing_reference:string,notes:string} */
    public array $subscriptionForm = [];

    /** @var array{requests_enabled:bool,expenses_enabled:bool,vendors_enabled:bool,budgets_enabled:bool,assets_enabled:bool,reports_enabled:bool,communications_enabled:bool,ai_enabled:bool,fintech_enabled:bool,procurement_enabled:bool,treasury_enabled:bool} */
    public array $entitlementsForm = [];

    /** @var array{amount:string,currency_code:string,payment_method:string,reference:string,received_at:string,period_start:string,period_end:string,note:string} */
    public array $paymentForm = [];

    protected array $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
        'planFilter' => ['except' => 'all'],
        'billingFilter' => ['except' => 'all'],
        'perPage' => ['except' => 10],
    ];

    public function mount(): void
    {
        $this->authorizePlatformOperator();
        $this->resetForms();
    }

    public function loadData(): void
    {
        if ($this->readyToLoad) {
            return;
        }

        // Never block UI rendering on billing automation refresh.
        $this->readyToLoad = true;

        try {
            app(TenantBillingAutomationService::class)->evaluateAllExternal(Auth::user());
        } catch (Throwable $exception) {
            report($exception);
            $this->setFeedbackError('Tenant list loaded, but billing automation sync failed.');
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        if (! in_array($this->statusFilter, ['all', 'active', 'suspended', 'inactive', 'archived'], true)) {
            $this->statusFilter = 'all';
        }

        $this->resetPage();
    }

    public function updatedPlanFilter(): void
    {
        if (! in_array($this->planFilter, ['all', 'pilot', 'growth', 'business', 'enterprise'], true)) {
            $this->planFilter = 'all';
        }

        $this->resetPage();
    }

    public function updatedBillingFilter(): void
    {
        if (! in_array($this->billingFilter, ['all', 'current', 'grace', 'overdue', 'suspended'], true)) {
            $this->billingFilter = 'all';
        }

        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50], true)) {
            $this->perPage = 10;
        }

        $this->resetPage();
    }

    public function updatedSubscriptionFormPlanCode(string $value): void
    {
        // New tenants should inherit selected plan defaults immediately.
        if (! $this->isEditingTenant) {
            $this->applyPlanDefaultsToForm($value);
        }
    }

    public function updatedSubscriptionFormPaymentExecutionMode(string $value): void
    {
        if ($value !== TenantExecutionModeService::MODE_EXECUTION_ENABLED) {
            $this->subscriptionForm['execution_provider'] = 'manual_ops';
            $this->subscriptionForm['execution_max_transaction_amount'] = '';
            $this->subscriptionForm['execution_daily_cap_amount'] = '';
            $this->subscriptionForm['execution_monthly_cap_amount'] = '';
            $this->subscriptionForm['execution_maker_checker_threshold_amount'] = '';
            $this->subscriptionForm['execution_allowed_channels'] = [];
            $this->subscriptionForm['execution_policy_notes'] = '';
        }
    }

    public function openCreateModal(): void
    {
        $this->authorizePlatformOperator();
        $this->isEditingTenant = false;
        $this->editingCompanyId = null;
        $this->showTenantModal = true;
        $this->resetValidation();
        $this->resetForms();
    }

    public function openEditModal(int $companyId): void
    {
        $this->authorizePlatformOperator();

        $company = $this->tenantCompaniesBaseQuery()
            ->with(['subscription', 'featureEntitlements'])
            ->findOrFail($companyId);

        $this->isEditingTenant = true;
        $this->editingCompanyId = $company->id;
        $this->showTenantModal = true;
        $this->resetValidation();

        $this->tenantForm = [
            'name' => (string) $company->name,
            'slug' => (string) $company->slug,
            'email' => (string) ($company->email ?? ''),
            'phone' => (string) ($company->phone ?? ''),
            'industry' => (string) ($company->industry ?? ''),
            'currency_code' => strtoupper((string) ($company->currency_code ?? 'NGN')),
            'timezone' => $this->normalizedTimezone($company->timezone),
            'address' => (string) ($company->address ?? ''),
            'lifecycle_status' => (string) ($company->lifecycle_status ?: ($company->is_active ? 'active' : 'inactive')),
        ];

        $subscription = $company->subscription;
        $this->originalPlanCode = $subscription?->plan_code;
        $this->originalSubscriptionStatus = $subscription?->subscription_status;
        $this->subscriptionForm = [
            'plan_code' => (string) ($subscription?->plan_code ?? 'pilot'),
            'subscription_status' => (string) ($subscription?->subscription_status ?? 'current'),
            'payment_execution_mode' => (string) ($subscription?->payment_execution_mode ?? TenantExecutionModeService::MODE_DECISION_ONLY),
            'execution_provider' => (string) ($subscription?->execution_provider ?? 'manual_ops'),
            'execution_max_transaction_amount' => $subscription?->execution_max_transaction_amount !== null ? (string) $subscription->execution_max_transaction_amount : '',
            'execution_daily_cap_amount' => $subscription?->execution_daily_cap_amount !== null ? (string) $subscription->execution_daily_cap_amount : '',
            'execution_monthly_cap_amount' => $subscription?->execution_monthly_cap_amount !== null ? (string) $subscription->execution_monthly_cap_amount : '',
            'execution_maker_checker_threshold_amount' => $subscription?->execution_maker_checker_threshold_amount !== null ? (string) $subscription->execution_maker_checker_threshold_amount : '',
            'execution_allowed_channels' => array_values((array) ($subscription?->execution_allowed_channels ?? [])),
            'execution_policy_notes' => (string) ($subscription?->execution_policy_notes ?? ''),
            'starts_at' => (string) optional($subscription?->starts_at)->toDateString(),
            'ends_at' => (string) optional($subscription?->ends_at)->toDateString(),
            'grace_until' => (string) optional($subscription?->grace_until)->toDateString(),
            'seat_limit' => $subscription?->seat_limit !== null ? (string) $subscription->seat_limit : '',
            'billing_reference' => (string) ($subscription?->billing_reference ?? ''),
            'notes' => (string) ($subscription?->notes ?? ''),
        ];

        $entitlements = $company->featureEntitlements;
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
            'procurement_enabled' => (bool) ($entitlements?->procurement_enabled ?? false),
            'treasury_enabled' => (bool) ($entitlements?->treasury_enabled ?? false),
        ];
    }

    public function closeTenantModal(): void
    {
        $this->showTenantModal = false;
        $this->resetValidation();
        $this->resetForms();
    }

    public function applyPlanDefaults(): void
    {
        $this->authorizePlatformOperator();

        $planCode = (string) ($this->subscriptionForm['plan_code'] ?? config('tenant_plans.default_plan', 'pilot'));
        $this->applyPlanDefaultsToForm($planCode);
        $this->setFeedback('Plan defaults applied. You can still adjust toggles before saving.');
    }

    public function saveTenant(): void
    {
        $this->authorizePlatformOperator();

        $tenantHasUsers = $this->isEditingTenant && $this->editingCompanyId
            ? User::query()->where('company_id', $this->editingCompanyId)->exists()
            : false;

        $requireLoginEmail = ! $this->isEditingTenant || ! $tenantHasUsers;

        $slugRules = ['required', 'string', 'max:120'];
        $slugUnique = Rule::unique('companies', 'slug');
        if ($this->isEditingTenant && $this->editingCompanyId) {
            $slugUnique = $slugUnique->ignore($this->editingCompanyId);
        }
        $slugRules[] = $slugUnique;

        $emailRules = [$requireLoginEmail ? 'required' : 'nullable', 'email', 'max:255'];
        if ($requireLoginEmail) {
            $emailRules[] = Rule::unique('users', 'email');
        }

        $this->validate([
            'tenantForm.name' => ['required', 'string', 'max:120'],
            'tenantForm.slug' => $slugRules,
            'tenantForm.email' => $emailRules,
            'tenantForm.phone' => ['nullable', 'string', 'max:50'],
            'tenantForm.industry' => ['nullable', 'string', 'max:100'],
            'tenantForm.currency_code' => ['required', 'string', 'size:3'],
            'tenantForm.timezone' => ['required', 'string', 'max:100'],
            'tenantForm.address' => ['nullable', 'string', 'max:1000'],
            'tenantForm.lifecycle_status' => ['required', Rule::in(['active', 'suspended', 'inactive', 'archived'])],
            'subscriptionForm.plan_code' => ['required', Rule::in(['pilot', 'growth', 'business', 'enterprise'])],
            'subscriptionForm.subscription_status' => ['required', Rule::in(['current', 'grace', 'overdue', 'suspended'])],
            'subscriptionForm.payment_execution_mode' => ['required', Rule::in(app(TenantExecutionModeService::class)->supportedModes())],
            'subscriptionForm.execution_provider' => ['nullable', 'string', 'max:80'],
            'subscriptionForm.execution_max_transaction_amount' => ['nullable', 'numeric', 'min:0.01'],
            'subscriptionForm.execution_daily_cap_amount' => ['nullable', 'numeric', 'min:0.01'],
            'subscriptionForm.execution_monthly_cap_amount' => ['nullable', 'numeric', 'min:0.01'],
            'subscriptionForm.execution_maker_checker_threshold_amount' => ['nullable', 'numeric', 'min:0.01'],
            'subscriptionForm.execution_allowed_channels' => ['array'],
            'subscriptionForm.execution_allowed_channels.*' => ['string', Rule::in(app(TenantExecutionModeService::class)->supportedChannels())],
            'subscriptionForm.execution_policy_notes' => ['nullable', 'string', 'max:1000'],
            'subscriptionForm.starts_at' => ['nullable', 'date'],
            'subscriptionForm.ends_at' => ['nullable', 'date'],
            'subscriptionForm.grace_until' => ['nullable', 'date'],
            'subscriptionForm.seat_limit' => ['nullable', 'integer', 'min:1'],
            'subscriptionForm.billing_reference' => ['nullable', 'string', 'max:100'],
            'subscriptionForm.notes' => ['nullable', 'string', 'max:1000'],
            'entitlementsForm.requests_enabled' => ['boolean'],
            'entitlementsForm.expenses_enabled' => ['boolean'],
            'entitlementsForm.vendors_enabled' => ['boolean'],
            'entitlementsForm.budgets_enabled' => ['boolean'],
            'entitlementsForm.assets_enabled' => ['boolean'],
            'entitlementsForm.reports_enabled' => ['boolean'],
            'entitlementsForm.communications_enabled' => ['boolean'],
            'entitlementsForm.ai_enabled' => ['boolean'],
            'entitlementsForm.fintech_enabled' => ['boolean'],
            'entitlementsForm.procurement_enabled' => ['boolean'],
            'entitlementsForm.treasury_enabled' => ['boolean'],
        ]);

        $user = Auth::user();
        if (! $user) {
            throw new AuthorizationException('User session is required.');
        }

        $provisionedLogin = null;

        try {
            $company = $this->isEditingTenant && $this->editingCompanyId
                ? Company::query()->findOrFail($this->editingCompanyId)
                : new Company();
            $isNewTenant = ! $company->exists;
            $existingSubscription = TenantSubscription::query()
                ->where('company_id', (int) $company->id)
                ->first();
            $existingEntitlements = TenantFeatureEntitlement::query()
                ->where('company_id', (int) $company->id)
                ->first();
            $previousSeatLimit = $existingSubscription?->seat_limit;
            $previousEntitlements = $existingEntitlements
                ? $this->entitlementMapFromModel($existingEntitlements)
                : null;
            $previousExecutionPolicy = $existingSubscription
                ? $this->executionPolicyMapFromSubscription($existingSubscription)
                : null;

            $executionPolicy = app(TenantExecutionModeService::class)->normalizeForSave(
                lifecycleStatus: (string) $this->tenantForm['lifecycle_status'],
                subscriptionStatus: (string) $this->subscriptionForm['subscription_status'],
                entitlements: $this->entitlementsForm,
                paymentExecutionMode: (string) ($this->subscriptionForm['payment_execution_mode'] ?? TenantExecutionModeService::MODE_DECISION_ONLY),
                provider: (string) ($this->subscriptionForm['execution_provider'] ?? ''),
                allowedChannels: $this->subscriptionForm['execution_allowed_channels'] ?? [],
                maxTransaction: $this->subscriptionForm['execution_max_transaction_amount'] ?? null,
                dailyCap: $this->subscriptionForm['execution_daily_cap_amount'] ?? null,
                monthlyCap: $this->subscriptionForm['execution_monthly_cap_amount'] ?? null,
                makerCheckerThreshold: $this->subscriptionForm['execution_maker_checker_threshold_amount'] ?? null,
                policyNotes: (string) ($this->subscriptionForm['execution_policy_notes'] ?? ''),
                companyId: $company->exists ? (int) $company->id : null,
            );

            $lifecycle = (string) $this->tenantForm['lifecycle_status'];
            $companyTimezone = $this->normalizedTimezone((string) ($this->tenantForm['timezone'] ?? ''));
            $company->forceFill([
                'name' => trim((string) $this->tenantForm['name']),
                'slug' => $this->resolveUniqueSlug((string) $this->tenantForm['slug'], $company->exists ? (int) $company->id : null),
                'email' => trim((string) $this->tenantForm['email']) !== '' ? trim((string) $this->tenantForm['email']) : null,
                'phone' => trim((string) $this->tenantForm['phone']) !== '' ? trim((string) $this->tenantForm['phone']) : null,
                'industry' => trim((string) $this->tenantForm['industry']) !== '' ? trim((string) $this->tenantForm['industry']) : null,
                'currency_code' => strtoupper((string) $this->tenantForm['currency_code']),
                'timezone' => $companyTimezone,
                'address' => trim((string) $this->tenantForm['address']) !== '' ? trim((string) $this->tenantForm['address']) : null,
                'lifecycle_status' => $lifecycle,
                'is_active' => $lifecycle === 'active',
                'status_reason' => $company->exists ? $company->status_reason : null,
                'status_updated_at' => now(),
                'created_by' => $company->exists ? $company->created_by : $user->id,
                'updated_by' => $user->id,
            ]);
            $company->save();

            $previousMode = (string) ($existingSubscription?->payment_execution_mode ?? TenantExecutionModeService::MODE_DECISION_ONLY);
            $newMode = (string) $executionPolicy['payment_execution_mode'];
            $isEnablingExecution = $previousMode !== TenantExecutionModeService::MODE_EXECUTION_ENABLED
                && $newMode === TenantExecutionModeService::MODE_EXECUTION_ENABLED;
            $isDisablingExecution = $previousMode === TenantExecutionModeService::MODE_EXECUTION_ENABLED
                && $newMode !== TenantExecutionModeService::MODE_EXECUTION_ENABLED;

            $trialDefaults = $this->trialWindowDefaultsForTimezone($companyTimezone);

            $subscription = TenantSubscription::query()->updateOrCreate(
                ['company_id' => $company->id],
                [
                    'plan_code' => (string) $this->subscriptionForm['plan_code'],
                    'subscription_status' => (string) $this->subscriptionForm['subscription_status'],
                    'payment_execution_mode' => $newMode,
                    'starts_at' => $this->normalizeDate($this->subscriptionForm['starts_at']),
                    'ends_at' => $this->normalizeDate($this->subscriptionForm['ends_at']),
                    'grace_until' => $this->normalizeDate($this->subscriptionForm['grace_until']),
                    'trial_started_at' => $existingSubscription?->trial_started_at ?? $trialDefaults['start'],
                    'trial_ends_at' => $existingSubscription?->trial_ends_at ?? $trialDefaults['end'],
                    'seat_limit' => $this->normalizeInteger($this->subscriptionForm['seat_limit']),
                    'execution_provider' => $executionPolicy['execution_provider'],
                    'execution_enabled_at' => $isEnablingExecution
                        ? now()
                        : ($isDisablingExecution ? null : $existingSubscription?->execution_enabled_at),
                    'execution_enabled_by' => $isEnablingExecution
                        ? $user->id
                        : ($isDisablingExecution ? null : $existingSubscription?->execution_enabled_by),
                    'execution_max_transaction_amount' => $executionPolicy['execution_max_transaction_amount'],
                    'execution_daily_cap_amount' => $executionPolicy['execution_daily_cap_amount'],
                    'execution_monthly_cap_amount' => $executionPolicy['execution_monthly_cap_amount'],
                    'execution_maker_checker_threshold_amount' => $executionPolicy['execution_maker_checker_threshold_amount'],
                    'execution_allowed_channels' => $executionPolicy['execution_allowed_channels'],
                    'execution_policy_notes' => $executionPolicy['execution_policy_notes'],
                    'billing_reference' => $this->nullableString($this->subscriptionForm['billing_reference']),
                    'notes' => $this->nullableString($this->subscriptionForm['notes']),
                    'created_by' => $existingSubscription?->created_by ?? $user->id,
                    'updated_by' => $user->id,
                ]
            );

            $entitlementsRecord = TenantFeatureEntitlement::query()->updateOrCreate(
                ['company_id' => $company->id],
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
                    'procurement_enabled' => (bool) $this->entitlementsForm['procurement_enabled'],
                    'treasury_enabled' => (bool) $this->entitlementsForm['treasury_enabled'],
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]
            );

            $provisionedLogin = $this->provisionTenantOwnerLoginIfMissing($company);
            $this->recordPlanHistoryIfChanged(
                company: $company,
                subscription: $subscription,
                previousPlanCode: $this->originalPlanCode,
                previousSubscriptionStatus: $this->originalSubscriptionStatus,
                actor: $user
            );

            app(TenantAuditLogger::class)->log(
                companyId: (int) $company->id,
                action: $isNewTenant ? 'tenant.created' : 'tenant.updated',
                actor: $user,
                description: $isNewTenant ? 'Tenant created from platform control center.' : 'Tenant profile/settings updated.',
                entityType: Company::class,
                entityId: (int) $company->id,
                metadata: [
                    'lifecycle_status' => $lifecycle,
                    'plan_code' => (string) $subscription->plan_code,
                    'subscription_status' => (string) $subscription->subscription_status,
                ],
            );

            $currentSeatLimit = $subscription->seat_limit;
            if ((int) ($previousSeatLimit ?? 0) !== (int) ($currentSeatLimit ?? 0)
                || ($previousSeatLimit === null) !== ($currentSeatLimit === null)) {
                app(TenantAuditLogger::class)->log(
                    companyId: (int) $company->id,
                    action: 'tenant.seat_limit.updated',
                    actor: $user,
                    description: 'Tenant seat policy updated.',
                    entityType: TenantSubscription::class,
                    entityId: (int) $subscription->id,
                    metadata: [
                        'previous_seat_limit' => $previousSeatLimit,
                        'new_seat_limit' => $currentSeatLimit,
                    ],
                );
            }

            $currentEntitlements = $this->entitlementMapFromModel($entitlementsRecord);
            $entitlementDiffs = $this->entitlementDifferences($previousEntitlements, $currentEntitlements);
            if ($entitlementDiffs !== []) {
                app(TenantAuditLogger::class)->log(
                    companyId: (int) $company->id,
                    action: 'tenant.entitlements.updated',
                    actor: $user,
                    description: 'Tenant module entitlements updated.',
                    entityType: TenantFeatureEntitlement::class,
                    entityId: (int) $entitlementsRecord->id,
                    metadata: [
                        'changes' => $entitlementDiffs,
                    ],
                );
            }

            $currentExecutionPolicy = $this->executionPolicyMapFromSubscription($subscription);
            $executionPolicyDiffs = $this->executionPolicyDifferences($previousExecutionPolicy, $currentExecutionPolicy);
            if ($executionPolicyDiffs !== []) {
                app(TenantAuditLogger::class)->log(
                    companyId: (int) $company->id,
                    action: 'tenant.execution_policy.updated',
                    actor: $user,
                    description: 'Tenant execution policy updated from platform control center.',
                    entityType: TenantSubscription::class,
                    entityId: (int) $subscription->id,
                    metadata: [
                        'changes' => $executionPolicyDiffs,
                    ],
                );
            }

            $previousMode = (string) ($previousExecutionPolicy['payment_execution_mode'] ?? TenantExecutionModeService::MODE_DECISION_ONLY);
            $currentMode = (string) ($currentExecutionPolicy['payment_execution_mode'] ?? TenantExecutionModeService::MODE_DECISION_ONLY);
            if ($previousMode !== $currentMode) {
                app(TenantAuditLogger::class)->log(
                    companyId: (int) $company->id,
                    action: 'tenant.execution_mode.updated',
                    actor: $user,
                    description: 'Tenant payment execution mode updated.',
                    entityType: TenantSubscription::class,
                    entityId: (int) $subscription->id,
                    metadata: [
                        'from' => $previousMode,
                        'to' => $currentMode,
                    ],
                );
            }

            app(TenantUsageSnapshotService::class)->capture((int) $company->id, $user);

            // Re-evaluate status after plan/dates/status edits.
            app(TenantBillingAutomationService::class)->evaluateCompany($company, $user);

            // Keep platform sidebar/context aligned to the last tenant touched.
            session(['platform_active_tenant_id' => (int) $company->id]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->setFeedbackError('Unable to save tenant right now.');

            return;
        }

        $message = $this->isEditingTenant ? 'Tenant updated.' : 'Tenant created.';
        if (! empty($provisionedLogin)) {
            $message .= sprintf(
                ' Login created: %s | Temporary password: %s',
                $provisionedLogin['email'],
                $provisionedLogin['temporary_password']
            );
        }

        $this->setFeedback($message);
        $this->closeTenantModal();
        $this->resetPage();
    }

    public function suspendTenant(int $companyId): void
    {
        $this->updateLifecycle($companyId, 'suspended', 'Suspended from tenant control center.');
    }

    public function activateTenant(int $companyId): void
    {
        $this->updateLifecycle($companyId, 'active', 'Reactivated from tenant control center.');
    }

    public function deactivateTenant(int $companyId): void
    {
        $this->updateLifecycle($companyId, 'inactive', 'Deactivated from tenant control center.');
    }

    public function openPaymentModal(int $companyId): void
    {
        $this->authorizePlatformOperator();

        $company = $this->tenantCompaniesBaseQuery()->findOrFail($companyId);

        $this->paymentCompanyId = $companyId;
        $this->paymentForm = [
            'amount' => '',
            'currency_code' => strtoupper((string) ($company->currency_code ?: 'NGN')),
            'payment_method' => 'offline_transfer',
            'reference' => '',
            'received_at' => Carbon::now($this->normalizedTimezone($company->timezone))->format('Y-m-d\\TH:i'),
            'period_start' => '',
            'period_end' => '',
            'note' => '',
        ];
        $this->showPaymentModal = true;
        $this->resetValidation();
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
        $this->paymentCompanyId = null;
        $this->paymentForm = [
            'amount' => '',
            'currency_code' => 'NGN',
            'payment_method' => 'offline_transfer',
            'reference' => '',
            'received_at' => '',
            'period_start' => '',
            'period_end' => '',
            'note' => '',
        ];
        $this->resetValidation();
    }

    public function saveManualPayment(): void
    {
        $this->authorizePlatformOperator();

        if (! $this->paymentCompanyId) {
            $this->setFeedbackError('Select a tenant before recording payment.');

            return;
        }

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

        $user = Auth::user();
        if (! $user) {
            throw new AuthorizationException('User session is required.');
        }

        try {
            $subscription = TenantSubscription::query()
                ->where('company_id', $this->paymentCompanyId)
                ->first();

            $payment = TenantManualPayment::query()->create([
                'company_id' => $this->paymentCompanyId,
                'tenant_subscription_id' => $subscription?->id,
                'amount' => (float) $this->paymentForm['amount'],
                'currency_code' => strtoupper((string) $this->paymentForm['currency_code']),
                'payment_method' => (string) $this->paymentForm['payment_method'],
                'reference' => $this->nullableString($this->paymentForm['reference']),
                'received_at' => (string) $this->paymentForm['received_at'],
                'period_start' => $this->normalizeDate($this->paymentForm['period_start']),
                'period_end' => $this->normalizeDate($this->paymentForm['period_end']),
                'note' => $this->nullableString($this->paymentForm['note']),
                'recorded_by' => $user->id,
            ]);

            $allocationStatus = $this->hasCoveragePeriod()
                ? 'allocated'
                : 'unapplied';

            TenantBillingAllocation::query()->create([
                'company_id' => $this->paymentCompanyId,
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
                'created_by' => $user->id,
            ]);

            TenantBillingLedgerEntry::query()->create([
                'company_id' => $this->paymentCompanyId,
                'tenant_subscription_id' => $subscription?->id,
                'source_type' => TenantManualPayment::class,
                'source_id' => $payment->id,
                'entry_type' => 'payment',
                'direction' => 'credit',
                'amount' => (float) $payment->amount,
                'currency_code' => (string) $payment->currency_code,
                'effective_date' => $payment->received_at->toDateString(),
                'period_start' => $payment->period_start?->toDateString(),
                'period_end' => $payment->period_end?->toDateString(),
                'description' => 'Offline payment received',
                'metadata' => [
                    'payment_method' => (string) $payment->payment_method,
                    'reference' => (string) ($payment->reference ?? ''),
                    'allocation_status' => $allocationStatus,
                ],
                'created_by' => $user->id,
            ]);

            app(TenantAuditLogger::class)->log(
                companyId: (int) $this->paymentCompanyId,
                action: 'tenant.billing.payment_recorded',
                actor: $user,
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
                    actor: $user
                );

                $subscription->refresh();
                $company = $this->tenantCompaniesBaseQuery()->find($this->paymentCompanyId);
                if ($company) {
                    $automationService->evaluateCompany($company, $user);
                }
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->setFeedbackError('Unable to record manual payment right now.');

            return;
        }

        $this->setFeedback('Manual payment recorded.');
        $this->closePaymentModal();
    }

    public function provisionTenantLogin(int $companyId): void
    {
        $this->authorizePlatformOperator();

        $company = $this->tenantCompaniesBaseQuery()->findOrFail($companyId);

        if (User::query()->where('company_id', $company->id)->exists()) {
            $this->setFeedbackError('Tenant already has at least one login user.');

            return;
        }

        try {
            $credentials = $this->provisionTenantOwnerLoginIfMissing($company);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->setFeedbackError('Unable to provision tenant login right now.');

            return;
        }

        if (! $credentials) {
            $this->setFeedbackError('Unable to provision tenant login right now.');

            return;
        }

        $actor = Auth::user();
        app(TenantAuditLogger::class)->log(
            companyId: (int) $company->id,
            action: 'tenant.login.provisioned',
            actor: $actor,
            description: 'Initial tenant owner login provisioned from platform control center.',
            entityType: Company::class,
            entityId: (int) $company->id,
            metadata: ['email' => (string) $credentials['email']],
        );
        app(TenantUsageSnapshotService::class)->capture((int) $company->id, $actor);

        $this->setFeedback(sprintf(
            'Login created: %s | Temporary password: %s',
            $credentials['email'],
            $credentials['temporary_password']
        ));
    }

    public function render(): View
    {
        $this->authorizePlatformOperator();

        $companies = $this->readyToLoad ? $this->companyRows() : $this->emptyPaginator();
        $stats = $this->readyToLoad ? $this->stats() : $this->emptyStats();

        return view('livewire.platform.tenant-management-page', [
            'companies' => $companies,
            'stats' => $stats,
        ]);
    }

    private function companyRows(): LengthAwarePaginator
    {
        return $this->tenantCompaniesBaseQuery()
            ->withCount('users')
            ->with(['subscription', 'featureEntitlements'])
            ->when($this->search !== '', function ($query): void {
                $search = trim($this->search);
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($this->statusFilter !== 'all', fn ($query) => $query->where('lifecycle_status', $this->statusFilter))
            ->when($this->planFilter !== 'all', fn ($query) => $query->whereHas('subscription', fn ($subQuery) => $subQuery->where('plan_code', $this->planFilter)))
            ->when($this->billingFilter !== 'all', fn ($query) => $query->whereHas('subscription', fn ($subQuery) => $subQuery->where('subscription_status', $this->billingFilter)))
            ->orderBy('name')
            ->paginate($this->perPage);
    }

    /**
     * @return array{
     *   total:int,
     *   active:int,
     *   suspended:int,
     *   overdue:int,
     *   current:int,
     *   month_payments:string
     * }
     */
    private function stats(): array
    {
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $tenantCompanyIds = $this->tenantCompaniesBaseQuery()->pluck('id');

        return [
            'total' => $this->tenantCompaniesBaseQuery()->count(),
            'active' => $this->tenantCompaniesBaseQuery()->where('lifecycle_status', 'active')->count(),
            'suspended' => $this->tenantCompaniesBaseQuery()->where('lifecycle_status', 'suspended')->count(),
            'overdue' => TenantSubscription::query()
                ->whereIn('company_id', $tenantCompanyIds)
                ->where('subscription_status', 'overdue')
                ->count(),
            'current' => TenantSubscription::query()
                ->whereIn('company_id', $tenantCompanyIds)
                ->where('subscription_status', 'current')
                ->count(),
            'month_payments' => (string) number_format((float) TenantManualPayment::query()
                ->whereIn('company_id', $tenantCompanyIds)
                ->whereBetween('received_at', [$monthStart, $monthEnd])
                ->sum('amount'), 2),
        ];
    }

    /**
     * @return array{
     *   total:int,
     *   active:int,
     *   suspended:int,
     *   overdue:int,
     *   current:int,
     *   month_payments:string
     * }
     */
    private function emptyStats(): array
    {
        return [
            'total' => 0,
            'active' => 0,
            'suspended' => 0,
            'overdue' => 0,
            'current' => 0,
            'month_payments' => '0.00',
        ];
    }

    private function updateLifecycle(int $companyId, string $status, string $reason): void
    {
        $this->authorizePlatformOperator();

        if (! in_array($status, ['active', 'suspended', 'inactive'], true)) {
            $this->setFeedbackError('Unsupported lifecycle status.');

            return;
        }

        $company = $this->tenantCompaniesBaseQuery()->findOrFail($companyId);
        $company->forceFill([
            'lifecycle_status' => $status,
            'is_active' => $status === 'active',
            'status_reason' => $reason,
            'status_updated_at' => now(),
            'updated_by' => Auth::id(),
        ])->save();

        app(TenantAuditLogger::class)->log(
            companyId: (int) $company->id,
            action: 'tenant.lifecycle.updated',
            actor: Auth::user(),
            description: 'Tenant lifecycle state updated.',
            entityType: Company::class,
            entityId: (int) $company->id,
            metadata: [
                'lifecycle_status' => $status,
                'reason' => $reason,
            ],
        );

        $this->setFeedback('Tenant lifecycle updated.');
    }

    private function tenantCompaniesBaseQuery(): Builder
    {
        $internalSlugs = $this->internalCompanySlugs();

        return Company::query()
            ->when(
                $internalSlugs !== [],
                fn (Builder $query) => $query->whereNotIn('slug', $internalSlugs)
            );
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

    /**
     * @return array{email:string,temporary_password:string}|null
     */
    private function provisionTenantOwnerLoginIfMissing(Company $company): ?array
    {
        if (User::query()->where('company_id', $company->id)->exists()) {
            return null;
        }

        $email = strtolower(trim((string) ($this->tenantForm['email'] ?? $company->email ?? '')));
        if ($email === '') {
            throw ValidationException::withMessages([
                'tenantForm.email' => 'Tenant email is required to provision the first login user.',
            ]);
        }

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'tenantForm.email' => 'This email is already used by another user.',
            ]);
        }

        $department = Department::query()
            ->where('company_id', $company->id)
            ->where('code', 'GENERAL')
            ->first();

        if (! $department) {
            $department = Department::query()->create([
                'company_id' => $company->id,
                'name' => 'General',
                'code' => 'GENERAL',
                'manager_user_id' => null,
                'is_active' => true,
            ]);
        }

        $temporaryPassword = (string) Str::password(12);

        $owner = User::query()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'name' => $company->name.' Admin',
            'email' => $email,
            'password' => $temporaryPassword,
            'role' => UserRole::Owner->value,
            'is_active' => true,
        ]);

        if (! $department->manager_user_id) {
            $department->forceFill(['manager_user_id' => $owner->id])->save();
        }

        return [
            'email' => $email,
            'temporary_password' => $temporaryPassword,
        ];
    }

    private function hasCoveragePeriod(): bool
    {
        return trim((string) $this->paymentForm['period_start']) !== ''
            && trim((string) $this->paymentForm['period_end']) !== '';
    }

    private function recordPlanHistoryIfChanged(
        Company $company,
        TenantSubscription $subscription,
        ?string $previousPlanCode,
        ?string $previousSubscriptionStatus,
        User $actor
    ): void {
        $previousPlan = $previousPlanCode;
        $previousStatus = $previousSubscriptionStatus;
        $newPlan = (string) $subscription->plan_code;
        $newStatus = (string) $subscription->subscription_status;

        $planChanged = $previousPlan !== null && $previousPlan !== $newPlan;
        $statusChanged = $previousStatus !== null && $previousStatus !== $newStatus;
        $newSubscriptionRecorded = $previousPlan === null && $previousStatus === null;

        if (! $planChanged && ! $statusChanged && ! $newSubscriptionRecorded) {
            return;
        }

        $history = TenantPlanChangeHistory::query()->create([
            'company_id' => (int) $company->id,
            'tenant_subscription_id' => (int) $subscription->id,
            'previous_plan_code' => $previousPlan,
            'new_plan_code' => $newPlan,
            'previous_subscription_status' => $previousStatus,
            'new_subscription_status' => $newStatus,
            'changed_at' => now(),
            'reason' => $newSubscriptionRecorded
                ? 'Initial subscription settings recorded.'
                : 'Plan/subscription status updated from tenant settings.',
            'changed_by' => $actor->id,
        ]);

        app(TenantAuditLogger::class)->log(
            companyId: (int) $company->id,
            action: 'tenant.plan.changed',
            actor: $actor,
            description: 'Tenant subscription plan/status changed.',
            entityType: TenantPlanChangeHistory::class,
            entityId: (int) $history->id,
            metadata: [
                'previous_plan' => $previousPlan,
                'new_plan' => $newPlan,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
            ],
        );
    }

    private function resetForms(): void
    {
        $this->originalPlanCode = null;
        $this->originalSubscriptionStatus = null;

        $this->tenantForm = [
            'name' => '',
            'slug' => '',
            'email' => '',
            'phone' => '',
            'industry' => '',
            'currency_code' => 'NGN',
            'timezone' => 'Africa/Lagos',
            'address' => '',
            'lifecycle_status' => 'active',
        ];

        $this->subscriptionForm = [
            'plan_code' => (string) config('tenant_plans.default_plan', 'pilot'),
            'subscription_status' => 'current',
            'payment_execution_mode' => TenantExecutionModeService::MODE_DECISION_ONLY,
            'execution_provider' => 'manual_ops',
            'execution_max_transaction_amount' => '',
            'execution_daily_cap_amount' => '',
            'execution_monthly_cap_amount' => '',
            'execution_maker_checker_threshold_amount' => '',
            'execution_allowed_channels' => [],
            'execution_policy_notes' => '',
            'starts_at' => '',
            'ends_at' => '',
            'grace_until' => '',
            'seat_limit' => '',
            'billing_reference' => '',
            'notes' => '',
        ];

        $this->entitlementsForm = [
            'requests_enabled' => true,
            'expenses_enabled' => true,
            'vendors_enabled' => true,
            'budgets_enabled' => true,
            'assets_enabled' => true,
            'reports_enabled' => true,
            'communications_enabled' => true,
            'ai_enabled' => false,
            'fintech_enabled' => false,
            'procurement_enabled' => false,
            'treasury_enabled' => false,
        ];

        $this->applyPlanDefaultsToForm((string) $this->subscriptionForm['plan_code']);

        $this->paymentForm = [
            'amount' => '',
            'currency_code' => 'NGN',
            'payment_method' => 'offline_transfer',
            'reference' => '',
            'received_at' => '',
            'period_start' => '',
            'period_end' => '',
            'note' => '',
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

    private function resolveUniqueSlug(string $value, ?int $ignoreCompanyId = null): string
    {
        $baseSlug = Str::slug($value);
        $rootSlug = $baseSlug !== '' ? $baseSlug : 'company';
        $slug = $rootSlug;
        $counter = 1;

        while (
            Company::query()
                ->when($ignoreCompanyId, fn ($query) => $query->whereKeyNot($ignoreCompanyId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $rootSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function normalizeDate(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizeInteger(string $value): ?int
    {
        $trimmed = trim($value);

        return $trimmed !== '' ? (int) $trimmed : null;
    }

    private function nullableString(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * @return array<string, bool>
     */
    private function entitlementMapFromModel(TenantFeatureEntitlement $entitlements): array
    {
        return [
            'requests_enabled' => (bool) $entitlements->requests_enabled,
            'expenses_enabled' => (bool) $entitlements->expenses_enabled,
            'vendors_enabled' => (bool) $entitlements->vendors_enabled,
            'budgets_enabled' => (bool) $entitlements->budgets_enabled,
            'assets_enabled' => (bool) $entitlements->assets_enabled,
            'reports_enabled' => (bool) $entitlements->reports_enabled,
            'communications_enabled' => (bool) $entitlements->communications_enabled,
            'ai_enabled' => (bool) $entitlements->ai_enabled,
            'fintech_enabled' => (bool) $entitlements->fintech_enabled,
            'procurement_enabled' => (bool) $entitlements->procurement_enabled,
            'treasury_enabled' => (bool) $entitlements->treasury_enabled,
        ];
    }

    /**
     * @param  array<string,bool>|null  $previous
     * @param  array<string,bool>  $current
     * @return array<string,array{from:bool|null,to:bool}>
     */
    private function entitlementDifferences(?array $previous, array $current): array
    {
        $diff = [];
        foreach ($current as $key => $value) {
            $before = $previous[$key] ?? null;
            if ($before === $value) {
                continue;
            }

            $diff[$key] = [
                'from' => $before,
                'to' => $value,
            ];
        }

        return $diff;
    }

    /**
     * @return array<string, mixed>
     */
    private function executionPolicyMapFromSubscription(TenantSubscription $subscription): array
    {
        return [
            'payment_execution_mode' => (string) ($subscription->payment_execution_mode ?? TenantExecutionModeService::MODE_DECISION_ONLY),
            'execution_provider' => (string) ($subscription->execution_provider ?? 'manual_ops'),
            'execution_max_transaction_amount' => $subscription->execution_max_transaction_amount !== null ? (float) $subscription->execution_max_transaction_amount : null,
            'execution_daily_cap_amount' => $subscription->execution_daily_cap_amount !== null ? (float) $subscription->execution_daily_cap_amount : null,
            'execution_monthly_cap_amount' => $subscription->execution_monthly_cap_amount !== null ? (float) $subscription->execution_monthly_cap_amount : null,
            'execution_maker_checker_threshold_amount' => $subscription->execution_maker_checker_threshold_amount !== null ? (float) $subscription->execution_maker_checker_threshold_amount : null,
            'execution_allowed_channels' => array_values((array) ($subscription->execution_allowed_channels ?? [])),
            'execution_policy_notes' => (string) ($subscription->execution_policy_notes ?? ''),
        ];
    }

    /**
     * @param  array<string,mixed>|null  $previous
     * @param  array<string,mixed>  $current
     * @return array<string,array{from:mixed,to:mixed}>
     */
    private function executionPolicyDifferences(?array $previous, array $current): array
    {
        $diff = [];
        foreach ($current as $key => $value) {
            $before = $previous[$key] ?? null;
            if (is_array($before) || is_array($value)) {
                $beforeNormalized = array_values((array) $before);
                $valueNormalized = array_values((array) $value);
                sort($beforeNormalized);
                sort($valueNormalized);
                if ($beforeNormalized === $valueNormalized) {
                    continue;
                }
                $diff[$key] = ['from' => $beforeNormalized, 'to' => $valueNormalized];
                continue;
            }

            if ($before === $value) {
                continue;
            }

            $diff[$key] = [
                'from' => $before,
                'to' => $value,
            ];
        }

        return $diff;
    }

    private function applyPlanDefaultsToForm(string $planCode): void
    {
        $defaultsService = app(TenantPlanDefaultsService::class);
        $this->entitlementsForm = array_merge(
            $this->entitlementsForm,
            $defaultsService->formEntitlementsForPlan($planCode)
        );

        $defaults = $defaultsService->defaultsForPlan($planCode);
        $seatLimit = $defaults['seat_limit'];
        $this->subscriptionForm['seat_limit'] = $seatLimit !== null ? (string) $seatLimit : '';
    }

    /**
     * @return array{grace_days:int,auto_suspend_days:int,automation_cadence:string}
     */
    private function billingDefaultsSummary(): array
    {
        return [
            'grace_days' => max(0, (int) config('platform.billing_default_grace_days', 3)),
            'auto_suspend_days' => max(1, (int) config('platform.billing_auto_suspend_after_days_overdue', 14)),
            'automation_cadence' => 'Hourly',
        ];
    }

    /**
     * @return array<int,array{code:string,label:string,enabled_modules:int,total_modules:int,default_seat_limit:int|null}>
     */
    private function planDefaultsSummary(): array
    {
        $plans = (array) config('tenant_plans.plans', []);
        $rows = [];

        foreach ($plans as $code => $config) {
            $entitlements = (array) ($config['entitlements'] ?? []);
            $enabled = collect($entitlements)->filter(static fn (mixed $value): bool => (bool) $value)->count();
            $total = count($entitlements);

            $rows[] = [
                'code' => (string) $code,
                'label' => (string) ($config['label'] ?? ucfirst((string) $code)),
                'enabled_modules' => $enabled,
                'total_modules' => $total,
                'default_seat_limit' => isset($config['default_seat_limit']) && $config['default_seat_limit'] !== null
                    ? (int) $config['default_seat_limit']
                    : null,
            ];
        }

        return $rows;
    }

    private function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $this->perPage, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    private function normalizedTimezone(?string $timezone): string
    {
        $candidate = trim((string) $timezone);
        $fallback = (string) config('app.timezone', 'Africa/Lagos');
        if ($candidate === '') {
            return $fallback;
        }
        return in_array($candidate, DateTimeZone::listIdentifiers(), true)
            ? $candidate
            : $fallback;
    }
    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function trialWindowDefaultsForTimezone(string $timezone): array
    {
        $tz = $this->normalizedTimezone($timezone);
        $start = Carbon::now($tz);
        $days = max(1, (int) config('platform.billing_default_trial_days', 14));
        return [
            'start' => $start->copy(),
            'end' => $start->copy()->addDays($days),
        ];
    }
    private function authorizePlatformOperator(): void
    {
        app(PlatformAccessService::class)->authorizePlatformOperator();
    }
}
































