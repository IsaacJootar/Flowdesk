<?php

namespace App\Livewire\Platform;

use App\Enums\PlatformUserRole;
use App\Models\User;
use App\Services\PlatformAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Platform Users')]
class PlatformUsersPage extends Component
{
    use WithPagination;

    public bool $readyToLoad = false;

    public string $search = '';

    public int $perPage = 10;

    /** @var array<int, string> */
    public array $roleDrafts = [];

    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    public bool $showCreateModal = false;

    /** @var array{name:string,email:string,password:string,password_confirmation:string,platform_role:string} */
    public array $createForm = [];

    public function mount(): void
    {
        $this->authorizePlatformOperator();
        $this->resetCreateForm();
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50], true)) {
            $this->perPage = 10;
        }

        $this->resetPage();
    }

    public function saveRole(int $userId): void
    {
        $this->authorizePlatformOwner();

        $selected = (string) ($this->roleDrafts[$userId] ?? 'none');
        $this->validate([
            "roleDrafts.$userId" => ['required', Rule::in(array_merge(['none'], PlatformUserRole::values()))],
        ]);

        $user = User::query()->whereNull('company_id')->findOrFail($userId);
        $previousRole = (string) ($user->platform_role ?? '');
        $nextRole = $selected === 'none' ? null : $selected;

        if ($user->id === (int) Auth::id() && $nextRole === null) {
            $this->setFeedbackError('You cannot remove your own platform role.');

            return;
        }

        if ($previousRole === PlatformUserRole::PlatformOwner->value && $nextRole !== PlatformUserRole::PlatformOwner->value) {
            $owners = User::query()
                ->whereNull('company_id')
                ->where('platform_role', PlatformUserRole::PlatformOwner->value)
                ->count();

            if ($owners <= 1) {
                $this->setFeedbackError('At least one platform owner must remain.');

                return;
            }
        }

        $user->forceFill(['platform_role' => $nextRole])->save();
        $this->roleDrafts[$userId] = (string) ($nextRole ?? 'none');
        $this->setFeedback('Platform role updated.');
    }

    public function openCreateModal(): void
    {
        $this->authorizePlatformOwner();
        $this->showCreateModal = true;
        $this->resetValidation();
        $this->resetCreateForm();
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetValidation();
        $this->resetCreateForm();
    }

    public function createPlatformUser(): void
    {
        $this->authorizePlatformOwner();

        $this->validate([
            'createForm.name' => ['required', 'string', 'max:120'],
            'createForm.email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'createForm.password' => ['required', 'string', 'min:8', 'confirmed'],
            'createForm.platform_role' => ['required', Rule::in(PlatformUserRole::values())],
        ]);

        User::query()->create([
            'name' => trim((string) $this->createForm['name']),
            'email' => strtolower(trim((string) $this->createForm['email'])),
            'password' => (string) $this->createForm['password'],
            'role' => 'owner',
            'platform_role' => (string) $this->createForm['platform_role'],
            'company_id' => null,
            'department_id' => null,
            'is_active' => true,
        ]);

        $this->closeCreateModal();
        $this->setFeedback('Platform user created.');
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorizePlatformOperator();

        $users = $this->readyToLoad
            ? User::query()
                ->whereNull('company_id')
                ->when($this->search !== '', function ($query): void {
                    $term = trim($this->search);
                    $query->where(function ($inner) use ($term): void {
                        $inner->where('name', 'like', '%'.$term.'%')
                            ->orWhere('email', 'like', '%'.$term.'%');
                    });
                })
                ->orderBy('name')
                ->paginate($this->perPage)
            : $this->emptyPaginator();

        if ($this->readyToLoad) {
            foreach ($users as $user) {
                if (! array_key_exists((int) $user->id, $this->roleDrafts)) {
                    $this->roleDrafts[(int) $user->id] = (string) ($user->platform_role ?? 'none');
                }
            }
        }

        $stats = $this->readyToLoad ? [
            'total' => User::query()->whereNull('company_id')->count(),
            'owners' => User::query()->whereNull('company_id')->where('platform_role', PlatformUserRole::PlatformOwner->value)->count(),
            'billing_admins' => User::query()->whereNull('company_id')->where('platform_role', PlatformUserRole::PlatformBillingAdmin->value)->count(),
            'ops_admins' => User::query()->whereNull('company_id')->where('platform_role', PlatformUserRole::PlatformOpsAdmin->value)->count(),
        ] : [
            'total' => 0,
            'owners' => 0,
            'billing_admins' => 0,
            'ops_admins' => 0,
        ];

        return view('livewire.platform.platform-users-page', [
            'users' => $users,
            'stats' => $stats,
            'roleOptions' => PlatformUserRole::cases(),
            'canManageRoles' => app(PlatformAccessService::class)->isPlatformOwner(Auth::user()),
        ]);
    }

    private function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $this->perPage, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    private function authorizePlatformOperator(): void
    {
        app(PlatformAccessService::class)->authorizePlatformOperator();
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

    private function authorizePlatformOwner(): void
    {
        app(PlatformAccessService::class)->authorizePlatformOwner();
    }

    private function resetCreateForm(): void
    {
        $this->createForm = [
            'name' => '',
            'email' => '',
            'password' => '',
            'password_confirmation' => '',
            'platform_role' => PlatformUserRole::PlatformOpsAdmin->value,
        ];
    }
}
