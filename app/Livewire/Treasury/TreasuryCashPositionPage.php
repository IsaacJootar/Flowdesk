<?php

namespace App\Livewire\Treasury;

use App\Domains\Treasury\Models\BankAccount;
use App\Domains\Treasury\Models\BankStatement;
use App\Domains\Treasury\Models\BankStatementLine;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Cash Position')]
class TreasuryCashPositionPage extends Component
{
    public bool $readyToLoad = false;

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function render(): View
    {
        $companyId = (int) auth()->user()->company_id;

        $accounts = $this->readyToLoad
            ? BankAccount::query()
                ->where('company_id', $companyId)
                ->orderByDesc('is_primary')
                ->orderBy('bank_name')
                ->get()
            : collect();

        $accountRows = $accounts->map(function (BankAccount $account) use ($companyId): array {
            $latestStatement = BankStatement::query()
                ->where('company_id', $companyId)
                ->where('bank_account_id', (int) $account->id)
                ->latest('id')
                ->first();

            $unreconciledQuery = BankStatementLine::query()
                ->where('company_id', $companyId)
                ->where('bank_account_id', (int) $account->id)
                ->where('is_reconciled', false);

            return [
                'bank_name' => (string) $account->bank_name,
                'account_name' => (string) $account->account_name,
                'currency_code' => (string) $account->currency_code,
                'last_statement_at' => optional($account->last_statement_at)->format('M d, Y H:i'),
                'latest_closing_balance' => (int) ($latestStatement?->closing_balance ?? 0),
                'unreconciled_count' => (int) (clone $unreconciledQuery)->count(),
                'unreconciled_value' => (int) (clone $unreconciledQuery)->sum('amount'),
            ];
        })->values();

        $summary = [
            'accounts' => (int) $accountRows->count(),
            'closing_balance_total' => (int) $accountRows->sum('latest_closing_balance'),
            'unreconciled_count' => (int) $accountRows->sum('unreconciled_count'),
            'unreconciled_value' => (int) $accountRows->sum('unreconciled_value'),
        ];

        return view('livewire.treasury.treasury-cash-position-page', [
            'accountRows' => $accountRows,
            'summary' => $summary,
        ]);
    }

    private function canAccessPage(User $user): bool
    {
        return Gate::forUser($user)->allows('viewAny', BankStatement::class);
    }
}
