<?php

namespace App\Livewire\Requests;

use App\Enums\UserRole;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Request Lifecycle Help')]
class RequestLifecycleGuidePage extends Component
{
    public bool $canOpenPayoutQueue = false;

    public function mount(): void
    {
        $user = auth()->user();

        abort_unless(
            in_array((string) ($user?->role ?? ''), [
                UserRole::Owner->value,
                UserRole::Finance->value,
                UserRole::Manager->value,
                UserRole::Auditor->value,
                UserRole::Staff->value,
            ], true),
            403
        );

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
