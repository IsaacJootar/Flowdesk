<?php

namespace App\Livewire\Organization;

use App\Actions\Company\AssignDepartmentManager;
use App\Actions\Company\CreateDepartment;
use App\Domains\Company\Models\Department;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class DepartmentsPage extends Component
{
    use WithPagination;

    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    public string $search = '';

    public int $perPage = 10;

    /** @var array{name: string, code: string, manager_user_id: string} */
    public array $departmentForm = [
        'name' => '',
        'code' => '',
        'manager_user_id' => '',
    ];

    /** @var array<int, string> */
    public array $departmentManagers = [];

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

    public function createDepartment(CreateDepartment $createDepartment): void
    {
        $this->authorizeOwner();

        try {
            $department = $createDepartment(auth()->user(), [
                'name' => $this->departmentForm['name'],
                'code' => $this->departmentForm['code'] ?: null,
                'manager_user_id' => $this->departmentForm['manager_user_id'] !== ''
                    ? (int) $this->departmentForm['manager_user_id']
                    : null,
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->setFeedbackError('Unable to create department right now.');

            return;
        }

        $this->departmentForm = ['name' => '', 'code' => '', 'manager_user_id' => ''];
        $this->departmentManagers[$department->id] = $department->manager_user_id ? (string) $department->manager_user_id : '';
        $this->setFeedback('Department created.');
        $this->resetPage();
    }

    public function saveDepartmentManager(int $departmentId, AssignDepartmentManager $assignDepartmentManager): void
    {
        $this->authorizeOwner();
        $department = Department::query()->findOrFail($departmentId);

        try {
            $assignDepartmentManager(auth()->user(), $department, [
                'manager_user_id' => ($this->departmentManagers[$departmentId] ?? '') !== ''
                    ? (int) $this->departmentManagers[$departmentId]
                    : null,
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->setFeedbackError('Unable to update department head.');

            return;
        }

        $this->setFeedback('Department head updated.');
    }

    public function render(): View
    {
        $this->authorizeOwner();

        $managerOptions = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $departments = Department::query()
            ->with(['manager:id,name'])
            ->withCount('users')
            ->when(
                $this->search !== '',
                fn ($query) => $query->where(function ($inner): void {
                    $inner->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('code', 'like', '%'.$this->search.'%');
                })
            )
            ->orderBy('name')
            ->paginate($this->perPage);

        foreach ($departments->items() as $department) {
            if (! array_key_exists($department->id, $this->departmentManagers)) {
                $this->departmentManagers[$department->id] = $department->manager_user_id
                    ? (string) $department->manager_user_id
                    : '';
            }
        }

        return view('livewire.organization.departments-page', [
            'departments' => $departments,
            'managerOptions' => $managerOptions,
        ])->layout('layouts.app', [
            'title' => 'Departments',
            'subtitle' => 'Create departments and maintain department head assignments',
        ]);
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
            throw new AuthorizationException('Only owner can manage departments.');
        }
    }
}
