<?php

namespace App\Livewire\Settings;

use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Fintech\Models\CompanyPaymentRailSetting;
use App\Enums\UserRole;
use App\Models\User;
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
#[Title('Payments Rails Integration')]
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

    public function connect(PaymentsRailSettingsService $settingsService, TenantAuditLogger $tenantAuditLogger): void
    {
        $this->authorizeOwner();
        $this->loadProviderOptions($settingsService);

        $this->validate([
            'connectForm.provider_key' => ['required', 'string', 'max:80', Rule::in($this->providerOptions)],
        ]);

        $user = auth()->user();
        $companyId = (int) $user->company_id;
        $provider = strtolower(trim((string) $this->connectForm['provider_key']));

        $setting = $settingsService->settingsForCompany($companyId);

        $setting->forceFill([
            'provider_key' => $provider,
            'connection_status' => CompanyPaymentRailSetting::STATUS_CONNECTED,
            'connected_at' => $setting->connected_at ?: now(),
            'paused_at' => null,
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
            ],
        );

        $this->setFeedback('Payment rail connected.');
        $this->hydrateForm($settingsService);
    }

    public function testConnection(PaymentsRailSettingsService $settingsService, TenantAuditLogger $tenantAuditLogger): void
    {
        $this->authorizeOwner();

        $user = auth()->user();
        $companyId = (int) $user->company_id;
        $setting = $settingsService->settingsForCompany($companyId);

        $provider = strtolower(trim((string) ($setting->provider_key ?: ($this->connectForm['provider_key'] ?? ''))));

        if ($provider === '') {
            $this->setFeedbackError('Select and connect a provider before running connection test.');

            return;
        }

        $result = $settingsService->testProvider($provider);

        $setting->forceFill([
            'provider_key' => $provider,
            'last_tested_at' => now(),
            'last_test_status' => $result['passed'] ? 'passed' : 'failed',
            'last_test_message' => (string) $result['message'],
            'updated_by' => (int) auth()->id(),
            'created_by' => $setting->created_by ?: (int) auth()->id(),
        ])->save();

        $tenantAuditLogger->log(
            companyId: $companyId,
            action: 'tenant.payments_rails.connection_tested',
            actor: $user,
            description: 'Payments rail connection test run from tenant settings page.',
            entityType: CompanyPaymentRailSetting::class,
            entityId: (int) $setting->id,
            metadata: [
                'provider_key' => $provider,
                'passed' => (bool) $result['passed'],
                'message' => (string) $result['message'],
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

        $user = auth()->user();
        $companyId = (int) $user->company_id;
        $setting = $settingsService->settingsForCompany($companyId);

        if ((string) $setting->connection_status !== CompanyPaymentRailSetting::STATUS_CONNECTED) {
            $this->setFeedbackError('Connect a payment rail before running sync.');

            return;
        }

        if (trim((string) $setting->provider_key) === '') {
            $this->setFeedbackError('Provider key is missing. Connect your rail again.');

            return;
        }

        $setting->forceFill([
            'last_synced_at' => now(),
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
            ],
        );

        $this->setFeedback('Sync completed.');
        $this->hydrateForm($settingsService);
    }

    public function togglePause(PaymentsRailSettingsService $settingsService, TenantAuditLogger $tenantAuditLogger): void
    {
        $this->authorizeOwner();

        $user = auth()->user();
        $companyId = (int) $user->company_id;
        $setting = $settingsService->settingsForCompany($companyId);
        $current = (string) ($setting->connection_status ?: CompanyPaymentRailSetting::STATUS_NOT_CONNECTED);

        if ($current === CompanyPaymentRailSetting::STATUS_PAUSED) {
            $setting->forceFill([
                'connection_status' => CompanyPaymentRailSetting::STATUS_CONNECTED,
                'paused_at' => null,
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

        $setting->forceFill([
            'connection_status' => CompanyPaymentRailSetting::STATUS_PAUSED,
            'paused_at' => now(),
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

    public function render(PaymentsRailSettingsService $settingsService): View
    {
        $this->authorizeOwner();

        $user = auth()->user();
        $user->loadMissing('company.subscription', 'company.featureEntitlements', 'company.paymentRailSetting');

        $subscription = $user->company?->subscription;
        $setting = $settingsService->settingsForCompany((int) $user->company_id);

        $executionMode = (string) ($subscription?->payment_execution_mode ?? 'decision_only');
        $providerKey = strtolower(trim((string) ($setting->provider_key ?: ($subscription?->execution_provider ?? ''))));
        $status = $this->resolveConnectionStatus($executionMode, (string) $setting->connection_status, $providerKey);

        return view('livewire.settings.payments-rails-integration-page', [
            'executionMode' => $executionMode,
            'providerKey' => $providerKey === '' ? 'not_set' : $providerKey,
            'status' => $status,
            'lastTestedAt' => $setting->last_tested_at?->format('M j, Y g:i A') ?? 'Never tested',
            'lastTestStatus' => (string) ($setting->last_test_status ?? 'not_run'),
            'lastTestMessage' => (string) ($setting->last_test_message ?? ''),
            'lastSyncedAt' => $setting->last_synced_at?->format('M j, Y g:i A') ?? 'No sync recorded',
            'isPaused' => (string) $setting->connection_status === CompanyPaymentRailSetting::STATUS_PAUSED,
            'isConnected' => (string) $setting->connection_status === CompanyPaymentRailSetting::STATUS_CONNECTED,
            'recentActions' => $this->recentActions((int) $user->company_id),
        ]);
    }

    /**
     * @return array{key:string,label:string,description:string,tone:string}
     */
    private function resolveConnectionStatus(string $executionMode, string $connectionStatus, string $providerKey): array
    {
        if ($connectionStatus === CompanyPaymentRailSetting::STATUS_PAUSED) {
            return [
                'key' => 'paused',
                'label' => 'Paused',
                'description' => 'Payments rail is paused. Resume when you are ready to continue processing.',
                'tone' => 'amber',
            ];
        }

        if ($connectionStatus === CompanyPaymentRailSetting::STATUS_CONNECTED && $executionMode === 'execution_enabled') {
            return [
                'key' => 'connected',
                'label' => 'Connected',
                'description' => 'Payments rail is connected and ready for execution-enabled processing.',
                'tone' => 'emerald',
            ];
        }

        if ($connectionStatus === CompanyPaymentRailSetting::STATUS_CONNECTED) {
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
                    'tenant.payments_rails.connection_tested' => 'Connection tested',
                    'tenant.payments_rails.sync_requested' => 'Sync now',
                    'tenant.payments_rails.paused' => 'Paused',
                    'tenant.payments_rails.resumed' => 'Resumed',
                    default => $action,
                };

                $result = 'Recorded';

                if (array_key_exists('passed', $metadata)) {
                    $result = ((bool) $metadata['passed']) ? 'Passed' : 'Failed';
                } elseif ($action === 'tenant.payments_rails.paused') {
                    $result = 'Paused';
                } elseif ($action === 'tenant.payments_rails.resumed') {
                    $result = 'Resumed';
                } elseif ($action === 'tenant.payments_rails.connected') {
                    $result = 'Connected';
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
            throw new AuthorizationException('Only admin (owner) can manage Payments Rails settings.');
        }
    }
}
