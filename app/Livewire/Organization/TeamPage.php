<?php

namespace App\Livewire\Organization;

use App\Actions\Company\CreateCompanyUser;
use App\Actions\Company\UpdateCompanyUserAssignment;
use App\Actions\Company\UpdateCompanyUserProfile;
use App\Domains\Company\Models\Department;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

class TeamPage extends Component
{
    use WithFileUploads;
    use WithPagination;

    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    public string $search = '';

    public int $perPage = 10;

    public mixed $avatarUpload = null;

    public mixed $profileAvatarUpload = null;

    public bool $showCreateModal = false;

    public bool $showProfileModal = false;

    public ?int $profileUserId = null;

    /** @var array{name: string, email: string, phone: string, gender: string, password: string, role: string, department_id: string, reports_to_user_id: string} */
    public array $newUserForm = [
        'name' => '',
        'email' => '',
        'phone' => '',
        'gender' => '',
        'password' => '',
        'role' => 'staff',
        'department_id' => '',
        'reports_to_user_id' => '',
    ];

    /** @var array{name: string, email: string, phone: string, gender: string} */
    public array $profileForm = [
        'name' => '',
        'email' => '',
        'phone' => '',
        'gender' => 'other',
    ];

    /** @var array<int, array{role: string, department_id: string, reports_to_user_id: string, is_active: bool}> */
    public array $userAssignments = [];

    protected array $queryString = [
        'search' => ['except' => ''],
        'perPage' => ['except' => 10],
    ];

    public function mount(): void
    {
        $this->authorizeOwner();
    }

    public function updatingSearch(): void
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

    public function openCreateModal(): void
    {
        $this->authorizeOwner();
        $this->resetValidation();
        $this->feedbackError = null;
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->avatarUpload = null;
        $this->newUserForm = [
            'name' => '',
            'email' => '',
            'phone' => '',
            'gender' => '',
            'password' => '',
            'role' => 'staff',
            'department_id' => '',
            'reports_to_user_id' => '',
        ];
        $this->resetValidation();
    }

    public function createCompanyUser(CreateCompanyUser $createCompanyUser): void
    {
        $this->authorizeOwner();

        try {
            $createCompanyUser(auth()->user(), [
                'name' => $this->newUserForm['name'],
                'email' => $this->newUserForm['email'],
                'phone' => $this->newUserForm['phone'] ?: null,
                'gender' => $this->newUserForm['gender'],
                'password' => $this->newUserForm['password'],
                'role' => $this->newUserForm['role'],
                'department_id' => (int) $this->newUserForm['department_id'],
                'reports_to_user_id' => $this->newUserForm['reports_to_user_id'] !== ''
                    ? (int) $this->newUserForm['reports_to_user_id']
                    : null,
                'avatar' => $this->avatarUpload,
            ]);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->normalizeValidationErrors($exception->errors()));
        } catch (Throwable $exception) {
            report($exception);
            $this->setFeedbackError('Unable to create team member.');

            return;
        }

        $this->newUserForm = [
            'name' => '',
            'email' => '',
            'phone' => '',
            'gender' => '',
            'password' => '',
            'role' => 'staff',
            'department_id' => '',
            'reports_to_user_id' => '',
        ];
        $this->avatarUpload = null;

        $this->setFeedback('Team member created.');
        $this->showCreateModal = false;
        $this->resetPage();
    }

    public function saveUserAssignment(int $userId, UpdateCompanyUserAssignment $updateCompanyUserAssignment): void
    {
        $this->authorizeOwner();
        $subject = User::query()->findOrFail($userId);
        $payload = $this->userAssignments[$userId] ?? null;

        if (! $payload) {
            $this->setFeedbackError('User assignment payload not found.');

            return;
        }

        try {
            $updateCompanyUserAssignment(auth()->user(), $subject, [
                'role' => $payload['role'],
                'department_id' => (int) $payload['department_id'],
                'reports_to_user_id' => $payload['reports_to_user_id'] !== '' ? (int) $payload['reports_to_user_id'] : null,
                'is_active' => (bool) $payload['is_active'],
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->setFeedbackError('Unable to update user assignment.');

            return;
        }

        $this->setFeedback('User assignment updated.');
    }

    public function openProfileModal(int $userId): void
    {
        $this->authorizeOwner();
        $user = User::query()->findOrFail($userId);

        $this->resetValidation();
        $this->feedbackError = null;
        $this->profileUserId = $user->id;
        $this->profileForm = [
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'phone' => (string) ($user->phone ?? ''),
            'gender' => (string) ($user->gender ?? 'other'),
        ];
        $this->profileAvatarUpload = null;
        $this->showProfileModal = true;
    }

    public function closeProfileModal(): void
    {
        $this->showProfileModal = false;
        $this->profileUserId = null;
        $this->profileForm = [
            'name' => '',
            'email' => '',
            'phone' => '',
            'gender' => 'other',
        ];
        $this->profileAvatarUpload = null;
        $this->resetValidation();
    }

    public function saveUserProfile(UpdateCompanyUserProfile $updateCompanyUserProfile): void
    {
        $this->authorizeOwner();

        if (! $this->profileUserId) {
            $this->setFeedbackError('No staff profile selected.');

            return;
        }

        $subject = User::query()->findOrFail($this->profileUserId);

        try {
            $updateCompanyUserProfile(auth()->user(), $subject, [
                'name' => $this->profileForm['name'],
                'email' => $this->profileForm['email'],
                'phone' => $this->profileForm['phone'] ?: null,
                'gender' => $this->profileForm['gender'],
                'avatar' => $this->profileAvatarUpload,
            ]);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->normalizeProfileValidationErrors($exception->errors()));
        } catch (Throwable $exception) {
            report($exception);
            $this->setFeedbackError('Unable to update staff profile.');

            return;
        }

        $this->setFeedback('Staff profile updated.');
        $this->closeProfileModal();
    }

    public function render(): View
    {
        $this->authorizeOwner();

        $departments = Department::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $users = User::query()
            ->with(['department:id,name', 'reportsTo:id,name'])
            ->when(
                $this->search !== '',
                fn ($query) => $query->where(function ($inner): void {
                    $inner->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%')
                        ->orWhere('role', 'like', '%'.$this->search.'%');
                })
            )
            ->orderBy('name')
            ->paginate($this->perPage);

        $managerOptions = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        foreach ($users->items() as $user) {
            if (! array_key_exists($user->id, $this->userAssignments)) {
                $this->userAssignments[$user->id] = [
                    'role' => $user->role,
                    'department_id' => (string) $user->department_id,
                    'reports_to_user_id' => $user->reports_to_user_id ? (string) $user->reports_to_user_id : '',
                    'is_active' => (bool) $user->is_active,
                ];
            }
        }

        return view('livewire.organization.team-page', [
            'departments' => $departments,
            'users' => $users,
            'managerOptions' => $managerOptions,
            'roles' => UserRole::values(),
        ])->layout('layouts.app', [
            'title' => 'Team',
            'subtitle' => 'Manage staff accounts, role ownership, and reporting hierarchy',
        ]);
    }

    public function getProfileUserProperty(): ?User
    {
        if (! $this->profileUserId) {
            return null;
        }

        return User::query()->find($this->profileUserId);
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
        if (! auth()->check() || auth()->user()->role !== UserRole::Owner->value) {
            throw new AuthorizationException('Only owner can manage team assignments.');
        }
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     * @return array<string, array<int, string>>
     */
    private function normalizeValidationErrors(array $errors): array
    {
        $mapped = [];
        $formFields = ['name', 'email', 'phone', 'gender', 'password', 'role', 'department_id', 'reports_to_user_id'];

        foreach ($errors as $key => $messages) {
            if (str_starts_with($key, 'newUserForm.')) {
                $mapped[$key] = $messages;
                continue;
            }

            if (in_array($key, $formFields, true)) {
                $mapped['newUserForm.'.$key] = $messages;
                continue;
            }

            if ($key === 'avatar') {
                $mapped['avatarUpload'] = $messages;
                continue;
            }

            $mapped[$key] = $messages;
        }

        return $mapped;
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     * @return array<string, array<int, string>>
     */
    private function normalizeProfileValidationErrors(array $errors): array
    {
        $mapped = [];
        $formFields = ['name', 'email', 'phone', 'gender'];

        foreach ($errors as $key => $messages) {
            if (str_starts_with($key, 'profileForm.')) {
                $mapped[$key] = $messages;
                continue;
            }

            if (in_array($key, $formFields, true)) {
                $mapped['profileForm.'.$key] = $messages;
                continue;
            }

            if ($key === 'avatar') {
                $mapped['profileAvatarUpload'] = $messages;
                continue;
            }

            $mapped[$key] = $messages;
        }

        return $mapped;
    }
}
