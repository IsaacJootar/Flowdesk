<?php

namespace App\Livewire\Requests;

use App\Domains\Requests\Models\SpendRequest;
use App\Enums\UserRole;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Request Tracker Guide')]
class RequestLifecycleGuidePage extends Component
{
    public bool $canOpenPayoutQueue = false;

    public function mount(): void
    {
        $user = auth()->user();

        abort_unless($user && Gate::forUser($user)->allows('viewAny', SpendRequest::class), 403);

        $this->canOpenPayoutQueue = in_array((string) ($user?->role ?? ''), [
            UserRole::Owner->value,
            UserRole::Finance->value,
            UserRole::Manager->value,
            UserRole::Auditor->value,
        ], true);
    }

    public function render(): View
    {
        return view('livewire.requests.request-lifecycle-guide-page');
    }
}
