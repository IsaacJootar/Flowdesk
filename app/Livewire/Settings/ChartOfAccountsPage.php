<?php

namespace App\Livewire\Settings;

use App\Actions\Accounting\SaveChartOfAccountMappings;
use App\Domains\Accounting\Models\ChartOfAccountMapping;
use App\Enums\AccountingCategory;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Chart of Accounts')]
class ChartOfAccountsPage extends Component
{
    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    public bool $canManage = false;

    /**
     * @var array<string, array{account_code: string, account_name: string, updated_by_name: string, updated_at_display: string}>
     */
    public array $mappings = [];

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canView($user), 403);

        $this->canManage = $this->canManage($user);
        $this->loadMappings();
    }

    public function save(SaveChartOfAccountMappings $saveChartOfAccountMappings): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        if (! $this->canManage($user)) {
            throw new AuthorizationException('Only owner and finance can update Chart of Accounts.');
        }

        $this->feedbackError = null;
        $saveChartOfAccountMappings($user, $this->mappings);
        $this->loadMappings();
        $this->setFeedback('Chart of Accounts saved.');
    }

    public function render(): View
    {
        $readyCount = collect($this->mappings)
            ->filter(fn (array $row): bool => trim((string) ($row['account_code'] ?? '')) !== '')
            ->count();
        $totalCount = count(AccountingCategory::values());

        return view('livewire.settings.chart-of-accounts-page', [
            'categories' => AccountingCategory::options(),
            'readyCount' => $readyCount,
            'totalCount' => $totalCount,
            'missingCount' => max(0, $totalCount - $readyCount),
            'allReady' => $readyCount === $totalCount,
        ]);
    }

    private function loadMappings(): void
    {
        $companyId = (int) auth()->user()->company_id;
        $existing = ChartOfAccountMapping::query()
            ->with('updater:id,name')
            ->where('company_id', $companyId)
            ->where('provider', 'csv')
            ->get(['id', 'category_key', 'account_code', 'account_name', 'updated_by', 'updated_at'])
            ->keyBy('category_key');

        $this->mappings = [];
        foreach (AccountingCategory::values() as $categoryKey) {
            $mapping = $existing->get($categoryKey);
            $this->mappings[$categoryKey] = [
                'account_code' => (string) ($mapping?->account_code ?? ''),
                'account_name' => (string) ($mapping?->account_name ?? ''),
                'updated_by_name' => (string) ($mapping?->updater?->name ?? ''),
                'updated_at_display' => $mapping?->updated_at ? $mapping->updated_at->format('M j, Y g:i A') : '',
            ];
        }
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

    private function setFeedback(string $message): void
    {
        $this->feedbackError = null;
        $this->feedbackMessage = $message;
        $this->feedbackKey++;
    }
}
