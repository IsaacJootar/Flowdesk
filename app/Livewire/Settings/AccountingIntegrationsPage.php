<?php

namespace App\Livewire\Settings;

use App\Actions\Accounting\UpdateAccountingIntegrationStatus;
use App\Domains\Accounting\Models\AccountingIntegration;
use App\Domains\Accounting\Models\AccountingProviderAccount;
use App\Domains\Accounting\Models\AccountingSyncEvent;
use App\Domains\Accounting\Models\ChartOfAccountMapping;
use App\Enums\AccountingCategory;
use App\Enums\AccountingProvider;
use App\Enums\AccountingSyncStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Accounting Integrations')]
class AccountingIntegrationsPage extends Component
{
    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    public bool $canManage = false;

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canView($user), 403);

        $this->canManage = $this->canManage($user);
    }

    public function setStatus(string $provider, string $status, UpdateAccountingIntegrationStatus $updateStatus): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canManage($user), 403);

        $this->feedbackError = null;
        $integration = $updateStatus($user, $provider, $status);
        $this->setFeedback(AccountingProvider::tryFrom((string) $integration->provider)?->label().' marked '.str_replace('_', ' ', (string) $integration->status).'.');
    }

    public function render(): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->canView($user), 403);

        $companyId = (int) $user->company_id;
        $integrations = AccountingIntegration::query()
            ->where('company_id', $companyId)
            ->whereIn('provider', $this->providerKeys())
            ->get()
            ->keyBy('provider');

        $rows = collect($this->providerKeys())->map(function (string $provider) use ($companyId, $integrations): array {
            $integration = $integrations->get($provider);

            return [
                'key' => $provider,
                'label' => AccountingProvider::tryFrom($provider)?->label() ?? ucfirst($provider),
                'status' => (string) ($integration?->status ?? 'disconnected'),
                'mapped_accounts' => ChartOfAccountMapping::query()
                    ->where('company_id', $companyId)
                    ->where('provider', $provider)
                    ->whereNotNull('account_code')
                    ->count(),
                'provider_accounts' => AccountingProviderAccount::query()
                    ->where('company_id', $companyId)
                    ->where('provider', $provider)
                    ->where('is_active', true)
                    ->count(),
                'failed_events' => AccountingSyncEvent::query()
                    ->where('company_id', $companyId)
                    ->where('provider', $provider)
                    ->where('status', AccountingSyncStatus::Failed->value)
                    ->count(),
                'last_synced_at' => $integration?->last_synced_at?->format('M d, Y H:i') ?? 'Not synced yet',
            ];
        })->values();

        $csvMappedCount = ChartOfAccountMapping::query()
            ->where('company_id', $companyId)
            ->where('provider', AccountingProvider::Csv->value)
            ->whereNotNull('account_code')
            ->count();

        return view('livewire.settings.accounting-integrations-page', [
            'rows' => $rows,
            'csvMappedCount' => $csvMappedCount,
            'totalCategories' => count(AccountingCategory::values()),
        ]);
    }

    private function canView(User $user): bool
    {
        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
            UserRole::Auditor->value,
        ], true);
    }

    private function canManage(User $user): bool
    {
        return in_array((string) $user->role, [
            UserRole::Owner->value,
            UserRole::Finance->value,
        ], true);
    }

    /**
     * @return array<int, string>
     */
    private function providerKeys(): array
    {
        return [
            AccountingProvider::QuickBooks->value,
            AccountingProvider::Sage->value,
            AccountingProvider::Xero->value,
        ];
    }

    private function setFeedback(string $message): void
    {
        $this->feedbackError = null;
        $this->feedbackMessage = $message;
        $this->feedbackKey++;
    }
}
