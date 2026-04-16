<?php

namespace App\Livewire\Settings;

use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Fintech\Models\CompanyPaymentRailSetting;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\PaymentsRails\PaymentsRailsRolloutService;
use App\Services\PaymentsRails\PaymentsRailSettingsService;
use App\Services\TenantAuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Payment Provider Controls')]
class PaymentsRailsIntegrationPage extends Component
{
    use WithPagination;

    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    /**
     * @var array{provider_key:string}
     */
    public array $connectForm = [
        'provider_key' => '',
    ];

    /** @var array<int, string> */
    public array $providerOptions = [];

    public function mount(PaymentsRailSettingsService $settingsService): void
    {
        $this->authorizeOwner();
        $this->loadProviderOptions($settingsService);
        $this->hydrateForm($settingsService);
    }

    public function connect(
        PaymentsRailSettingsService $settingsService,
        PaymentsRailsRolloutService $rolloutService,
        TenantAuditLogger $tenantAuditLogger
    ): void {
        $this->authorizeOwner();
        $this->loadProviderOptions($settingsService);

        $this->validate([
            'connectForm.provider_key' => ['required', 'string', 'max:80', Rule::in($this->providerOptions)],
        ]);

        /** @var User $user */
        $user = auth()->user();
        $companyId = (int) $user->company_id;
        $provider = strtolower(trim((string) $this->connectForm['provider_key']));

        $user->loadMissing('company:id,slug');
        $companySlug = strtolower(trim((string) ($user->company?->slug ?? '')));
        $policy = $rolloutService->connectionPolicy($provider, $companySlug);

        if (! $policy['allowed']) {
            $this->setFeedbackError((string) ($policy['message'] ?: 'Selected provider is not available yet for this organization.'));

            return;
        }

        $result = $settingsService->connectProvider($provider, (bool) $policy['sandbox_mode']);

        $setting = $settingsService->settingsForCompany($companyId);
        $metadata = is_array($setting->metadata) ? $setting->metadata : [];
        $metadata['rollout_stage'] = (string) $policy['stage'];
        $metadata['sandbox_mode'] = (bool) $policy['sandbox_mode'];
        $metadata = $this->applyHealthMetadata($metadata, $result);

        if (! $result['passed']) {
            $setting->forceFill([
                'last_tested_at' => now(),
                'last_test_status' => 'failed',
                'last_test_message' => (string) $result['message'],
                'metadata' => $metadata,
                'updated_by' => (int) auth()->id(),
                'created_by' => $setting->created_by ?: (int) auth()->id(),
            ])->save();

            $tenantAuditLogger->log(
                companyId: $companyId,
                action: 'tenant.payments_rails.connect_failed',
                actor: $user,
                description: 'Payments rail connect failed from tenant settings page.',
                entityType: CompanyPaymentRailSetting::class,
                entityId: (int) $setting->id,
                metadata: [
                    'provider_key' => $provider,
                    'rollout_stage' => (string) $policy['stage'],
                    'sandbox_mode' => (bool) $policy['sandbox_mode'],
                    'health_status' => (string) $result['health_status'],
                    'webhook_status' => (string) $result['webhook_status'],
                    'message' => (string) $result['message'],
                    'details' => (array) $result['details'],
                ],
            );

            $this->setFeedbackError((string) $result['message']);
            $this->hydrateForm($settingsService);

            return;
        }

        $setting->forceFill([
            'provider_key' => $provider,
            'connection_status' => CompanyPaymentRailSetting::STATUS_CONNECTED,
            'connected_at' => $setting->connected_at ?: now(),
            'paused_at' => null,
            'metadata' => $metadata,
            'updated_by' => (int) auth()->id(),
            'created_by' => $setting->created_by ?: (int) auth()->id(),
        ])->save();

        $tenantAuditLogger->log(
            companyId: $companyId,
            action: 'tenant.payments_rails.connected',
            actor: $user,
            description: 'Payments rail connected from tenant settings page.',
            entityType: CompanyPaymentRailSetting::class,
            entityId: (int) $setting->id,
            metadata: [
                'provider_key' => $provider,
                'connection_status' => (string) $setting->connection_status,
                'rollout_stage' => (string) $policy['stage'],
                'sandbox_mode' => (bool) $policy['sandbox_mode'],
                'health_status' => (string) $result['health_status'],
                'webhook_status' => (string) $result['webhook_status'],
                'details' => (array) $result['details'],
            ],
        );

        $this->setFeedback((string) $result['message']);
        $this->hydrateForm($settingsService);
    }

    public function testConnection(
        PaymentsRailSettingsService $settingsService,
        PaymentsRailsRolloutService $rolloutService,
        TenantAuditLogger $tenantAuditLogger
    ): void {
        $this->authorizeOwner();

        /** @var User $user */
        $user = auth()->user();
        $companyId = (int) $user->company_id;
        $setting = $settingsService->settingsForCompany($companyId);

        $provider = strtolower(trim((string) ($setting->provider_key ?: ($this->connectForm['provider_key'] ?? ''))));

        if ($provider === '') {
            $this->setFeedbackError('Select and connect a provider before running connection test.');

            return;
        }

        $metadata = is_array($setting->metadata) ? $setting->metadata : [];
        $sandboxMode = (bool) ($metadata['sandbox_mode'] ?? false);

        // If stage metadata was not persisted yet, derive from staged rollout policy.
        if (! array_key_exists('sandbox_mode', $metadata)) {
            $user->loadMissing('company:id,slug');
            $companySlug = strtolower(trim((string) ($user->company?->slug ?? '')));
            $sandboxMode = (bool) ($rolloutService->connectionPolicy($provider, $companySlug)['sandbox_mode'] ?? false);
        }

        $result = $settingsService->testProvider($provider, $sandboxMode);
        $metadata = $this->applyHealthMetadata($metadata, $result);

        $setting->forceFill([
            'provider_key' => $provider,
            'last_tested_at' => now(),
            'last_test_status' => $result['passed'] ? 'passed' : 'failed',
            'last_test_message' => (string) $result['message'],
            'metadata' => $metadata,
            'updated_by' => (int) auth()->id(),
            'created_by' => $setting->created_by ?: (int) auth()->id(),
        ])->save();

        $tenantAuditLogger->log(
            companyId: $companyId,
            action: $result['passed'] ? 'tenant.payments_rails.connection_tested' : 'tenant.payments_rails.connection_test_failed',
            actor: $user,
            description: 'Payments rail connection test run from tenant settings page.',
            entityType: CompanyPaymentRailSetting::class,
            entityId: (int) $setting->id,
            metadata: [
                'provider_key' => $provider,
                'sandbox_mode' => $sandboxMode,
                'passed' => (bool) $result['passed'],
                'health_status' => (string) $result['health_status'],
                'webhook_status' => (string) $result['webhook_status'],
                'message' => (string) $result['message'],
                'details' => (array) $result['details'],
            ],
        );

        if ($result['passed']) {
            $this->setFeedback((string) $result['message']);
        } else {
            $this->setFeedbackError((string) $result['message']);
        }

        $this->hydrateForm($settingsService);
    }

    public function syncNow(PaymentsRailSettingsService $settingsService, TenantAuditLogger $tenantAuditLogger): void
    {
        $this->authorizeOwner();

        /** @var User $user */
        $user = auth()->user();
        $companyId = (int) $user->company_id;
        $setting = $settingsService->settingsForCompany($companyId);

        if ((string) $setting->connection_status !== CompanyPaymentRailSetting::STATUS_CONNECTED) {
            $this->setFeedbackError('Connect a payment rail before running sync.');

            return;
        }

        $provider = trim((string) $setting->provider_key);
        if ($provider === '') {
            $this->setFeedbackError('Provider key is missing. Connect your rail again.');

            return;
        }

        $metadata = is_array($setting->metadata) ? $setting->metadata : [];
        $sandboxMode = (bool) ($metadata['sandbox_mode'] ?? false);

        $result = $settingsService->syncProvider($provider, $sandboxMode);
        $metadata = $this->applyHealthMetadata($metadata, $result);

        if (! $result['passed']) {
            $setting->forceFill([
                'last_tested_at' => now(),
                'last_test_status' => 'failed',
                'last_test_message' => (string) $result['message'],
                'metadata' => $metadata,
                'updated_by' => (int) auth()->id(),
                'created_by' => $setting->created_by ?: (int) auth()->id(),
            ])->save();

            $tenantAuditLogger->log(
                companyId: $companyId,
                action: 'tenant.payments_rails.sync_failed',
                actor: $user,
                description: 'Payments rail sync failed from tenant settings page.',
                entityType: CompanyPaymentRailSetting::class,
                entityId: (int) $setting->id,
                metadata: [
                    'provider_key' => (string) $setting->provider_key,
                    'sandbox_mode' => $sandboxMode,
                    'health_status' => (string) $result['health_status'],
                    'webhook_status' => (string) $result['webhook_status'],
                    'message' => (string) $result['message'],
                    'details' => (array) $result['details'],
                ],
            );

            $this->setFeedbackError((string) $result['message']);
            $this->hydrateForm($settingsService);

            return;
        }

        $setting->forceFill([
            'last_synced_at' => now(),
            'metadata' => $metadata,
            'updated_by' => (int) auth()->id(),
            'created_by' => $setting->created_by ?: (int) auth()->id(),
        ])->save();

        $tenantAuditLogger->log(
            companyId: $companyId,
            action: 'tenant.payments_rails.sync_requested',
            actor: $user,
            description: 'Payments rail sync requested from tenant settings page.',
            entityType: CompanyPaymentRailSetting::class,
            entityId: (int) $setting->id,
            metadata: [
                'provider_key' => (string) $setting->provider_key,
                'connection_status' => (string) $setting->connection_status,
                'rollout_stage' => (string) ($metadata['rollout_stage'] ?? 'manual'),
                'sandbox_mode' => $sandboxMode,
                'health_status' => (string) $result['health_status'],
                'webhook_status' => (string) $result['webhook_status'],
                'details' => (array) $result['details'],
            ],
        );

        $this->setFeedback((string) $result['message']);
        $this->hydrateForm($settingsService);
    }

    public function togglePause(PaymentsRailSettingsService $settingsService, TenantAuditLogger $tenantAuditLogger): void
    {
        $this->authorizeOwner();

        /** @var User $user */
        $user = auth()->user();
        $companyId = (int) $user->company_id;
        $setting = $settingsService->settingsForCompany($companyId);
        $current = (string) ($setting->connection_status ?: CompanyPaymentRailSetting::STATUS_NOT_CONNECTED);

        if ($current === CompanyPaymentRailSetting::STATUS_PAUSED) {
            $provider = strtolower(trim((string) ($setting->provider_key ?? '')));
            $metadata = is_array($setting->metadata) ? $setting->metadata : [];
            $sandboxMode = (bool) ($metadata['sandbox_mode'] ?? false);
            $resumeCheck = $settingsService->testProvider($provider, $sandboxMode);

            if (! $resumeCheck['passed']) {
                $metadata = $this->applyHealthMetadata($metadata, $resumeCheck);

                $setting->forceFill([
                    'metadata' => $metadata,
                    'updated_by' => (int) auth()->id(),
                    'created_by' => $setting->created_by ?: (int) auth()->id(),
                ])->save();

                $tenantAuditLogger->log(
                    companyId: $companyId,
                    action: 'tenant.payments_rails.resume_failed',
                    actor: $user,
                    description: 'Payments rail resume failed due to failed readiness check.',
                    entityType: CompanyPaymentRailSetting::class,
                    entityId: (int) $setting->id,
                    metadata: [
                        'provider_key' => (string) $setting->provider_key,
                        'sandbox_mode' => $sandboxMode,
                        'health_status' => (string) $resumeCheck['health_status'],
                        'webhook_status' => (string) $resumeCheck['webhook_status'],
                        'message' => (string) $resumeCheck['message'],
                        'details' => (array) $resumeCheck['details'],
                    ],
                );

                $this->setFeedbackError((string) $resumeCheck['message']);
                $this->hydrateForm($settingsService);

                return;
            }

            $metadata = $this->applyHealthMetadata($metadata, $resumeCheck);

            $setting->forceFill([
                'connection_status' => CompanyPaymentRailSetting::STATUS_CONNECTED,
                'paused_at' => null,
                'metadata' => $metadata,
                'updated_by' => (int) auth()->id(),
                'created_by' => $setting->created_by ?: (int) auth()->id(),
            ])->save();

            $tenantAuditLogger->log(
                companyId: $companyId,
                action: 'tenant.payments_rails.resumed',
                actor: $user,
                description: 'Payments rail resumed from tenant settings page.',
                entityType: CompanyPaymentRailSetting::class,
                entityId: (int) $setting->id,
                metadata: [
                    'provider_key' => (string) $setting->provider_key,
                ],
            );

            $this->setFeedback('Payment rail resumed.');
            $this->hydrateForm($settingsService);

            return;
        }

        if ($current !== CompanyPaymentRailSetting::STATUS_CONNECTED) {
            $this->setFeedbackError('Connect a payment rail before pausing it.');

            return;
        }

        $metadata = is_array($setting->metadata) ? $setting->metadata : [];
        $metadata['rail_health_status'] = 'paused';
        $metadata['rail_health_message'] = 'Payment rail is paused by admin.';
        $metadata['rail_health_checked_at'] = now()->toDateTimeString();

        $setting->forceFill([
            'connection_status' => CompanyPaymentRailSetting::STATUS_PAUSED,
            'paused_at' => now(),
            'metadata' => $metadata,
            'updated_by' => (int) auth()->id(),
            'created_by' => $setting->created_by ?: (int) auth()->id(),
        ])->save();

        $tenantAuditLogger->log(
            companyId: $companyId,
            action: 'tenant.payments_rails.paused',
            actor: $user,
            description: 'Payments rail paused from tenant settings page.',
            entityType: CompanyPaymentRailSetting::class,
            entityId: (int) $setting->id,
            metadata: [
                'provider_key' => (string) $setting->provider_key,
            ],
        );

        $this->setFeedback('Payment rail paused.');
        $this->hydrateForm($settingsService);
    }

    public function render(PaymentsRailSettingsService $settingsService, PaymentsRailsRolloutService $rolloutService): View
    {
        $this->authorizeOwner();

        /** @var User $user */
        $user = auth()->user();
        $user->loadMissing('company.subscription', 'company.featureEntitlements', 'company.paymentRailSetting');

        $subscription = $user->company?->subscription;
        $setting = $settingsService->settingsForCompany((int) $user->company_id);

        $executionMode = (string) ($subscription?->payment_execution_mode ?? 'decision_only');
        $providerKey = strtolower(trim((string) ($setting->provider_key ?: ($subscription?->execution_provider ?? ''))));
        $metadata = is_array($setting->metadata) ? $setting->metadata : [];
        $sandboxMode = (bool) ($metadata['sandbox_mode'] ?? false);
        $companySlug = strtolower(trim((string) ($user->company?->slug ?? '')));
        $rolloutPolicy = $rolloutService->connectionPolicy($providerKey, $companySlug);
        $rolloutStage = (string) ($metadata['rollout_stage'] ?? $rolloutPolicy['stage'] ?? 'manual');

        $status = $this->resolveConnectionStatus(
            $executionMode,
            (string) $setting->connection_status,
            $providerKey,
            $sandboxMode,
            $rolloutStage,
        );

        $rolloutSummary = $this->rolloutSummary($rolloutStage);

        return view('livewire.settings.payments-rails-integration-page', [
            'executionMode' => $executionMode,
            'providerKey' => $providerKey === '' ? 'not_set' : $providerKey,
            'status' => $status,
            'health' => $this->healthSummary((string) $setting->connection_status, $metadata),
            'webhook' => $this->webhookSummary($providerKey, $metadata),
            'lastTestedAt' => $setting->last_tested_at?->format('M j, Y g:i A') ?? 'Never tested',
            'lastTestStatus' => (string) ($setting->last_test_status ?? 'not_run'),
            'lastTestMessage' => (string) ($setting->last_test_message ?? ''),
            'lastSyncedAt' => $setting->last_synced_at?->format('M j, Y g:i A') ?? 'No sync recorded',
            'isPaused' => (string) $setting->connection_status === CompanyPaymentRailSetting::STATUS_PAUSED,
            'isConnected' => (string) $setting->connection_status === CompanyPaymentRailSetting::STATUS_CONNECTED,
            'recentActions' => $this->recentActions((int) $user->company_id),
            'rolloutStageLabel' => $rolloutSummary['label'],
            'rolloutStageNote' => $rolloutSummary['note'],
        ]);
    }

    /**
     * @return array{key:string,label:string,description:string,tone:string}
     */
    private function resolveConnectionStatus(
        string $executionMode,
        string $connectionStatus,
        string $providerKey,
        bool $sandboxMode,
        string $rolloutStage,
    ): array {
        if ($connectionStatus === CompanyPaymentRailSetting::STATUS_PAUSED) {
            return [
                'key' => 'paused',
                'label' => 'Paused',
                'description' => 'Payments rail is paused. Resume when you are ready to continue processing.',
                'tone' => 'amber',
            ];
        }

        if ($connectionStatus === CompanyPaymentRailSetting::STATUS_CONNECTED && $executionMode === 'execution_enabled') {
            if ($sandboxMode && $providerKey !== '' && $providerKey !== 'manual_ops') {
                return [
                    'key' => 'connected_sandbox',
                    'label' => 'Connected (sandbox)',
                    'description' => 'Provider is connected in sandbox pilot mode. Live mode requires go-live approval.',
                    'tone' => 'indigo',
                ];
            }

            return [
                'key' => 'connected',
                'label' => 'Connected',
                'description' => 'Payments rail is connected and ready for execution-enabled processing.',
                'tone' => 'emerald',
            ];
        }

        if ($connectionStatus === CompanyPaymentRailSetting::STATUS_CONNECTED) {
            if ($sandboxMode && $providerKey !== '' && $providerKey !== 'manual_ops') {
                return [
                    'key' => 'connected_policy_only_sandbox',
                    'label' => 'Connected (sandbox)',
                    'description' => 'Provider is connected in sandbox mode. Switch to execution-enabled when go-live is approved.',
                    'tone' => 'indigo',
                ];
            }

            return [
                'key' => 'connected_policy_only',
                'label' => 'Connected (activation pending)',
                'description' => 'Provider is connected. Enable execution mode to process live payments.',
                'tone' => 'indigo',
            ];
        }

        if ($executionMode === 'execution_enabled' && $providerKey === '') {
            return [
                'key' => 'action_needed',
                'label' => 'Action needed',
                'description' => 'Execution mode is enabled, but no provider is connected yet.',
                'tone' => 'amber',
            ];
        }

        if ($rolloutStage === 'blocked') {
            return [
                'key' => 'rollout_blocked',
                'label' => 'Staged rollout',
                'description' => 'External provider rollout is not enabled for this organization yet. Use manual operations mode for now.',
                'tone' => 'amber',
            ];
        }

        return [
            'key' => 'policy_only',
            'label' => 'Policy-only mode',
            'description' => 'Execution is currently in decision-only mode. Connect rail now or continue in policy-only mode.',
            'tone' => 'slate',
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array{time:string,action:string,actor:string,result:string}>
     */
    private function recentActions(int $companyId): LengthAwarePaginator
    {
        return TenantAuditEvent::query()
            ->where('company_id', $companyId)
            ->where('action', 'like', 'tenant.payments_rails.%')
            ->with(['actor:id,name'])
            ->latest('event_at')
            ->paginate(10, ['*'], 'railActionPage')
            ->through(function (TenantAuditEvent $event): array {
                $action = (string) $event->action;
                $metadata = is_array($event->metadata) ? $event->metadata : [];

                $label = match ($action) {
                    'tenant.payments_rails.connected' => 'Connected',
                    'tenant.payments_rails.connect_failed' => 'Connect failed',
                    'tenant.payments_rails.connection_tested' => 'Connection tested',
                    'tenant.payments_rails.connection_test_failed' => 'Connection test failed',
                    'tenant.payments_rails.sync_requested' => 'Sync now',
                    'tenant.payments_rails.sync_failed' => 'Sync failed',
                    'tenant.payments_rails.paused' => 'Paused',
                    'tenant.payments_rails.resumed' => 'Resumed',
                    'tenant.payments_rails.resume_failed' => 'Resume failed',
                    default => $action,
                };

                $result = 'Recorded';

                if (array_key_exists('passed', $metadata)) {
                    $result = ((bool) $metadata['passed']) ? 'Passed' : 'Failed';
                } elseif (str_contains($action, '_failed')) {
                    $result = 'Failed';
                } elseif ($action === 'tenant.payments_rails.paused') {
                    $result = 'Paused';
                } elseif ($action === 'tenant.payments_rails.resumed') {
                    $result = 'Resumed';
                } elseif ($action === 'tenant.payments_rails.connected') {
                    $stage = strtolower(trim((string) ($metadata['rollout_stage'] ?? '')));
                    $result = $stage === 'sandbox' ? 'Connected (sandbox)' : 'Connected';
                } elseif ($action === 'tenant.payments_rails.sync_requested') {
                    $result = 'Synced';
                }

                return [
                    'time' => $event->event_at?->format('M j, Y g:i A') ?? '-',
                    'action' => $label,
                    'actor' => $event->actor?->name ?: 'System',
                    'result' => $result,
                ];
            });
    }

    private function loadProviderOptions(PaymentsRailSettingsService $settingsService): void
    {
        $this->providerOptions = $settingsService->providerOptions();
    }

    private function hydrateForm(PaymentsRailSettingsService $settingsService): void
    {
        $setting = $settingsService->settingsForCompany((int) auth()->user()->company_id);
        $provider = strtolower(trim((string) ($setting->provider_key ?? '')));

        $this->connectForm = [
            'provider_key' => $provider,
        ];

        if ($provider !== '' && ! in_array($provider, $this->providerOptions, true)) {
            $this->providerOptions[] = $provider;
        }
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

    private function authorizeOwner(): void
    {
        $user = auth()->user();

        if (! $user instanceof User || (string) $user->role !== UserRole::Owner->value) {
            throw new AuthorizationException('Only admin (owner) can manage payment provider controls.');
        }
    }

    /**
     * @param  array<string,mixed>  $metadata
     * @param  array{passed:bool,message:string,health_status:string,webhook_status:string,details:array<string,mixed>}  $result
     * @return array<string,mixed>
     */
    private function applyHealthMetadata(array $metadata, array $result): array
    {
        $metadata['rail_health_status'] = (string) $result['health_status'];
        $metadata['rail_health_message'] = (string) $result['message'];
        $metadata['rail_health_checked_at'] = now()->toDateTimeString();
        $metadata['webhook_status'] = (string) $result['webhook_status'];
        $metadata['last_diagnostics'] = (array) $result['details'];

        return $metadata;
    }

    /**
     * @param  array<string,mixed>  $metadata
     * @return array{label:string,tone:string,note:string}
     */
    private function healthSummary(string $connectionStatus, array $metadata): array
    {
        if ($connectionStatus === CompanyPaymentRailSetting::STATUS_PAUSED) {
            return [
                'label' => 'Paused',
                'tone' => 'amber',
                'note' => 'Rail is paused until resumed by admin.',
            ];
        }

        $status = strtolower(trim((string) ($metadata['rail_health_status'] ?? '')));
        $message = trim((string) ($metadata['rail_health_message'] ?? ''));

        return match ($status) {
            'healthy' => [
                'label' => 'Healthy',
                'tone' => 'emerald',
                'note' => $message !== '' ? $message : 'Provider checks are passing.',
            ],
            'degraded' => [
                'label' => 'Degraded',
                'tone' => 'amber',
                'note' => $message !== '' ? $message : 'Provider checks are unstable. Investigate credentials/network.',
            ],
            'paused' => [
                'label' => 'Paused',
                'tone' => 'amber',
                'note' => $message !== '' ? $message : 'Rail is paused until resumed.',
            ],
            default => [
                'label' => 'Action needed',
                'tone' => 'slate',
                'note' => $message !== '' ? $message : 'Run connection test to verify provider readiness.',
            ],
        };
    }

    /**
     * @param  array<string,mixed>  $metadata
     * @return array{label:string,tone:string,note:string}
     */
    private function webhookSummary(string $providerKey, array $metadata): array
    {
        if ($providerKey === '' || $providerKey === 'not_set') {
            return [
                'label' => 'Not checked',
                'tone' => 'slate',
                'note' => 'Connect a provider to validate webhook signature readiness.',
            ];
        }

        $status = strtolower(trim((string) ($metadata['webhook_status'] ?? '')));

        return match ($status) {
            'ready' => [
                'label' => 'Ready',
                'tone' => 'emerald',
                'note' => 'Webhook signature verification is configured.',
            ],
            'optional' => [
                'label' => 'Optional',
                'tone' => 'indigo',
                'note' => 'Manual operations mode does not require provider webhook signature checks.',
            ],
            'missing' => [
                'label' => 'Missing setup',
                'tone' => 'amber',
                'note' => 'Configure webhook signing secret/hash before using live callbacks.',
            ],
            default => [
                'label' => 'Not checked',
                'tone' => 'slate',
                'note' => 'Run a connection test to validate webhook signature setup.',
            ],
        };
    }

    /**
     * @return array{label:string,note:string}
     */
    private function rolloutSummary(string $stage): array
    {
        return match ($stage) {
            'live' => [
                'label' => 'Live approved',
                'note' => 'Your organization is approved for live provider usage.',
            ],
            'sandbox' => [
                'label' => 'Sandbox pilot',
                'note' => 'Your organization is in pilot mode. Keep provider tests in sandbox until go-live approval.',
            ],
            'blocked' => [
                'label' => 'Staged rollout',
                'note' => 'External providers are not enabled for this organization yet. Use manual operations mode for now.',
            ],
            default => [
                'label' => 'Manual operations',
                'note' => 'manual_ops is the default mode until provider pilot/go-live is enabled.',
            ],
        };
    }
}
