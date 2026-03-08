<?php

namespace App\Livewire\Treasury;

use App\Domains\Treasury\Models\BankStatement;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Treasury Reconciliation Help')]
class TreasuryReconciliationGuidePage extends Component
{
    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);
    }

    public function render(): View
    {
        return view('livewire.treasury.treasury-reconciliation-guide-page');
    }

    private function canAccessPage(User $user): bool
    {
        return Gate::forUser($user)->allows('viewAny', BankStatement::class);
    }
}
