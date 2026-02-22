<?php

namespace App\Livewire\Settings;

use App\Actions\Approvals\AddApprovalWorkflowStep;
use App\Actions\Approvals\CreateApprovalWorkflow;
use App\Actions\Approvals\DeleteApprovalWorkflow;
use App\Actions\Approvals\SetApprovalWorkflowDefault;
use App\Actions\Company\AssignDepartmentManager;
use App\Actions\Company\CreateCompanyUser;
use App\Actions\Company\CreateDepartment;
use App\Actions\Company\UpdateCompanyUserAssignment;
use App\Domains\Approvals\Models\ApprovalWorkflow;
use App\Domains\Requests\Models\SpendRequest;
use App\Domains\Company\Models\Department;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\ApprovalWorkflowStepOrderService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Throwable;

class OrganizationHierarchyPage extends Component
{
    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    /** @var array{name: string, code: string, manager_user_id: string} */
    public array $departmentForm = [
        'name' => '',
        'code' => '',
        'manager_user_id' => '',
    ];

    /** @var array<int, string> */
    public array $departmentManagers = [];

    /** @var array{name: string, email: string, phone: string, gender: string, password: string, role: string, department_id: string, reports_to_user_id: string} */
    public array $newUserForm = [
        'name' => '',
        'email' => '',
        'phone' => '',
        'gender' => 'other',
        'password' => '',
        'role' => 'staff',
        'department_id' => '',
        'reports_to_user_id' => '',
    ];

    /** @var array<int, array{role: string, department_id: string, reports_to_user_id: string, is_active: bool}> */
    public array $userAssignments = [];

    /** @var array{name: string, code: string, description: string, is_default: bool} */
    public array $workflowForm = [
        'name' => '',
        'code' => '',
        'description' => '',
        'is_default' => true,
    ];

    /** @var array{workflow_id: string, approver_source: string, approver_value: string, min_amount: string, max_amount: string} */
    public array $stepForm = [
        'workflow_id' => '',
        'approver_source' => 'reports_to',
        'approver_value' => '',
        'min_amount' => '',
        'max_amount' => '',
    ];

    public function mount(): void
    {
        $this->authorizeOwner();
        $this->normalizeStepOrdersAndLabels();
        $this->loadDepartmentAssignments();
        $this->loadUserAssignments();
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
            $this->setFeedbackError('Unable to update department manager.');

            return;
        }

        $this->setFeedback('Department manager updated.');
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
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->setFeedbackError('Unable to create team member.');

            return;
        }

        $this->newUserForm = [
            'name' => '',
            'email' => '',
            'phone' => '',
            'gender' => 'other',
            'password' => '',
            'role' => 'staff',
            'department_id' => '',
            'reports_to_user_id' => '',
        ];

        $this->loadUserAssignments();
        $this->setFeedback('Team member created.');
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

    public function createWorkflow(CreateApprovalWorkflow $createApprovalWorkflow): void
    {
        $this->authorizeOwner();

        try {
            $workflow = $createApprovalWorkflow(auth()->user(), [
                'name' => $this->workflowForm['name'],
                'code' => $this->workflowForm['code'] ?: null,
                'description' => $this->workflowForm['description'] ?: null,
                'is_default' => (bool) $this->workflowForm['is_default'],
                'applies_to' => 'request',
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->setFeedbackError('Unable to create approval workflow.');

            return;
        }

        $this->workflowForm = [
            'name' => '',
            'code' => '',
            'description' => '',
            'is_default' => false,
        ];
        $this->stepForm['workflow_id'] = (string) $workflow->id;
        $this->setFeedback('Approval workflow created.');
    }

    public function createPresetWorkflow(
        CreateApprovalWorkflow $createApprovalWorkflow,
        AddApprovalWorkflowStep $addApprovalWorkflowStep,
        SetApprovalWorkflowDefault $setApprovalWorkflowDefault
    ): void {
        $this->authorizeOwner();
        $owner = auth()->user();
        $presetCode = 'preset_standard_request_2step';
        $presetName = 'Standard Request Approval';

        try {
            $matchingWorkflows = ApprovalWorkflow::withoutGlobalScopes()
                ->with(['steps' => fn ($query) => $query->where('is_active', true)->orderBy('step_order')])
                ->where('company_id', $owner->company_id)
                ->where('applies_to', 'request')
                ->where(function ($query) use ($presetCode, $presetName): void {
                    $query->where('code', $presetCode)
                        ->orWhere('name', $presetName);
                })
                ->orderBy('id')
                ->get();

            $workflow = $matchingWorkflows->first();

            if ($matchingWorkflows->count() > 1) {
                $matchingWorkflows->slice(1)->each(function (ApprovalWorkflow $duplicateWorkflow): void {
                    $duplicateWorkflow->forceFill(['is_default' => false])->save();
                    $duplicateWorkflow->delete();
                });
            }

            if ($workflow && method_exists($workflow, 'trashed') && $workflow->trashed()) {
                $workflow->restore();
                $workflow->forceFill(['is_active' => true])->save();
                $workflow->load(['steps' => fn ($query) => $query->where('is_active', true)->orderBy('step_order')]);
            }

            if (! $workflow) {
                $workflow = $createApprovalWorkflow($owner, [
                    'name' => $presetName,
                    'code' => $presetCode,
                    'description' => 'Preset chain: direct manager approval followed by finance approval.',
                    'is_default' => true,
                    'applies_to' => 'request',
                ]);

                $addApprovalWorkflowStep($owner, $workflow, [
                    'actor_type' => 'reports_to',
                    'actor_value' => null,
                    'step_key' => 'direct_manager_review',
                    'step_order' => 1,
                ]);

                $addApprovalWorkflowStep($owner, $workflow, [
                    'actor_type' => 'role',
                    'actor_value' => UserRole::Finance->value,
                    'step_key' => 'finance_signoff',
                    'step_order' => 2,
                ]);

                $this->applyDefaultLabelsToWorkflowSteps($workflow);
                $this->normalizeStepOrdersAndLabels();
                $this->stepForm['workflow_id'] = (string) $workflow->id;
                $this->setFeedback('Preset workflow created: Direct Manager -> Finance.');

                return;
            }

            $workflow->load(['steps' => fn ($query) => $query->where('is_active', true)->orderBy('step_order')]);

            $hasReportsTo = $workflow->steps->contains(
                fn ($step): bool => $step->actor_type === 'reports_to'
            );
            $hasFinanceRole = $workflow->steps->contains(
                fn ($step): bool => $step->actor_type === 'role' && $step->actor_value === UserRole::Finance->value
            );

            if (! $hasReportsTo) {
                $addApprovalWorkflowStep($owner, $workflow, [
                    'actor_type' => 'reports_to',
                    'actor_value' => null,
                    'step_key' => 'direct_manager_review',
                    'step_order' => null,
                ]);
            }

            if (! $hasFinanceRole) {
                $addApprovalWorkflowStep($owner, $workflow, [
                    'actor_type' => 'role',
                    'actor_value' => UserRole::Finance->value,
                    'step_key' => 'finance_signoff',
                    'step_order' => null,
                ]);
            }

            $this->removeDuplicatePresetStepTypes($workflow);
            $this->applyDefaultLabelsToWorkflowSteps($workflow);
            $this->normalizeStepOrdersAndLabels();

            if (! $workflow->is_default) {
                $setApprovalWorkflowDefault($owner, $workflow);
            }

            $this->stepForm['workflow_id'] = (string) $workflow->id;
            if ($hasReportsTo && $hasFinanceRole && $workflow->is_default) {
                $this->setFeedback('Preset workflow already exists and is already set as default.');
            } else {
                $this->setFeedback('Preset workflow is ready and set as default.');
            }
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $this->setFeedbackError((string) ($errors['code'][0] ?? $errors['name'][0] ?? 'Unable to create preset workflow.'));

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->setFeedbackError('Unable to create preset workflow right now.');
        }
    }

    public function addWorkflowStep(AddApprovalWorkflowStep $addApprovalWorkflowStep): void
    {
        $this->authorizeOwner();

        if ($this->stepForm['workflow_id'] === '') {
            $this->addError('stepForm.workflow_id', 'Select a workflow first.');

            return;
        }

        $workflow = ApprovalWorkflow::query()->findOrFail((int) $this->stepForm['workflow_id']);
        $approverSource = $this->stepForm['approver_source'];
        $approverValue = null;

        if (in_array($approverSource, ['role', 'user'], true)) {
            if ($this->stepForm['approver_value'] === '') {
                $this->addError('stepForm.approver_value', 'Select an approver target for this source.');

                return;
            }

            $approverValue = $this->stepForm['approver_value'];
        }
        $stepKey = $this->defaultStepKeyForApproverSource($approverSource, $approverValue);

        try {
            $addApprovalWorkflowStep(auth()->user(), $workflow, [
                'step_order' => null,
                'step_key' => $stepKey,
                'actor_type' => $approverSource,
                'actor_value' => $approverValue,
                'min_amount' => $this->stepForm['min_amount'] !== '' ? (int) $this->stepForm['min_amount'] : null,
                'max_amount' => $this->stepForm['max_amount'] !== '' ? (int) $this->stepForm['max_amount'] : null,
            ]);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            if (array_key_exists('actor_value', $errors)) {
                $this->addError('stepForm.approver_value', (string) ($errors['actor_value'][0] ?? 'Invalid approver target.'));

                return;
            }

            if (array_key_exists('min_amount', $errors)) {
                $this->addError('stepForm.min_amount', (string) ($errors['min_amount'][0] ?? 'Invalid min amount.'));

                return;
            }

            if (array_key_exists('max_amount', $errors)) {
                $this->addError('stepForm.max_amount', (string) ($errors['max_amount'][0] ?? 'Invalid max amount.'));

                return;
            }

            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->setFeedbackError('Unable to add workflow step.');

            return;
        }

        $this->stepForm['approver_source'] = 'reports_to';
        $this->stepForm['approver_value'] = '';
        $this->stepForm['min_amount'] = '';
        $this->stepForm['max_amount'] = '';
        $this->normalizeStepOrdersAndLabels();
        $this->setFeedback('Workflow step added.');
    }

    public function updatedStepFormApproverSource(): void
    {
        $this->stepForm['approver_value'] = '';
        $this->resetErrorBag('stepForm.approver_value');
    }

    public function setDefaultWorkflow(int $workflowId, SetApprovalWorkflowDefault $setApprovalWorkflowDefault): void
    {
        $this->authorizeOwner();
        $workflow = ApprovalWorkflow::query()->findOrFail($workflowId);

        try {
            $setApprovalWorkflowDefault(auth()->user(), $workflow);
        } catch (Throwable $exception) {
            report($exception);
            $this->setFeedbackError('Unable to set default workflow.');

            return;
        }

        $this->setFeedback('Default workflow updated.');
    }

    public function deleteWorkflow(int $workflowId, DeleteApprovalWorkflow $deleteApprovalWorkflow): void
    {
        $this->authorizeOwner();
        $workflow = ApprovalWorkflow::query()->findOrFail($workflowId);

        try {
            $deleteApprovalWorkflow(auth()->user(), $workflow);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $this->setFeedbackError((string) ($errors['workflow'][0] ?? 'Unable to delete workflow.'));

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->setFeedbackError('Unable to delete workflow.');

            return;
        }

        if ($this->stepForm['workflow_id'] === (string) $workflowId) {
            $this->stepForm['workflow_id'] = '';
        }

        $this->normalizeStepOrdersAndLabels();
        $this->setFeedback('Workflow deleted.');
    }

    public function cleanupDuplicateWorkflows(
        AddApprovalWorkflowStep $addApprovalWorkflowStep,
        SetApprovalWorkflowDefault $setApprovalWorkflowDefault
    ): void {
        $this->authorizeOwner();
        $owner = auth()->user();

        $workflows = ApprovalWorkflow::query()
            ->with(['steps' => fn ($query) => $query->where('is_active', true)->orderBy('step_order')])
            ->where('applies_to', 'request')
            ->orderBy('id')
            ->get();

        $removedCount = 0;
        $skippedCount = 0;

        $groups = $workflows->groupBy(function (ApprovalWorkflow $workflow): string {
            $code = trim((string) ($workflow->code ?? ''));

            return $code !== '' ? 'code:'.$code : 'name:'.Str::slug((string) $workflow->name, '_');
        });

        foreach ($groups as $group) {
            if ($group->count() <= 1) {
                continue;
            }

            $keeper = $group->firstWhere('is_default', true) ?? $group->first();
            if (! $keeper) {
                continue;
            }

            foreach ($group as $workflow) {
                if ((int) $workflow->id === (int) $keeper->id) {
                    continue;
                }

                $linkedRequests = SpendRequest::query()
                    ->where('company_id', $workflow->company_id)
                    ->where('workflow_id', $workflow->id)
                    ->exists();

                if ($linkedRequests) {
                    $skippedCount++;
                    continue;
                }

                foreach ($workflow->steps as $duplicateStep) {
                    $existsOnKeeper = $keeper->steps->contains(function ($keeperStep) use ($duplicateStep): bool {
                        return $keeperStep->actor_type === $duplicateStep->actor_type
                            && (string) ($keeperStep->actor_value ?? '') === (string) ($duplicateStep->actor_value ?? '')
                            && (int) ($keeperStep->min_amount ?? -1) === (int) ($duplicateStep->min_amount ?? -1)
                            && (int) ($keeperStep->max_amount ?? -1) === (int) ($duplicateStep->max_amount ?? -1);
                    });

                    if (! $existsOnKeeper) {
                        $addApprovalWorkflowStep($owner, $keeper, [
                            'step_order' => null,
                            'step_key' => $duplicateStep->step_key ?: null,
                            'actor_type' => $duplicateStep->actor_type,
                            'actor_value' => $duplicateStep->actor_value ?: null,
                            'min_amount' => $duplicateStep->min_amount,
                            'max_amount' => $duplicateStep->max_amount,
                        ]);
                        $keeper->load(['steps' => fn ($query) => $query->where('is_active', true)->orderBy('step_order')]);
                    }
                }

                $this->removeDuplicatePresetStepTypes($workflow);
                $workflow->steps()->update([
                    'is_active' => false,
                    'deleted_at' => now(),
                ]);
                $workflow->forceFill(['is_default' => false, 'is_active' => false])->save();
                $workflow->delete();
                $removedCount++;
            }

            if (! $keeper->is_default) {
                $setApprovalWorkflowDefault($owner, $keeper);
            }

            $this->applyDefaultLabelsToWorkflowSteps($keeper);
        }

        if ($removedCount === 0 && $skippedCount === 0) {
            $this->normalizeStepOrdersAndLabels();
            $this->setFeedback('No duplicates found.');

            return;
        }

        $this->normalizeStepOrdersAndLabels();
        $message = 'Duplicate cleanup completed. Removed '.$removedCount.' duplicate workflow(s)';
        if ($skippedCount > 0) {
            $message .= ', skipped '.$skippedCount.' linked workflow(s)';
        }
        $message .= '.';

        $this->setFeedback($message);
    }

    public function render(): View
    {
        $this->authorizeOwner();

        $departments = Department::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'manager_user_id']);

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'department_id', 'reports_to_user_id', 'is_active']);

        $workflows = ApprovalWorkflow::query()
            ->with(['steps' => fn ($query) => $query->where('is_active', true)->orderBy('step_order')])
            ->where('applies_to', 'request')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        if ($this->stepForm['workflow_id'] === '' && $workflows->isNotEmpty()) {
            $this->stepForm['workflow_id'] = (string) $workflows->first()->id;
        }

        return view('livewire.settings.organization-hierarchy-page', [
            'departments' => $departments,
            'users' => $users,
            'workflows' => $workflows,
            'roles' => UserRole::values(),
        ])->layout('layouts.app', [
            'title' => 'Organization Hierarchy',
            'subtitle' => 'Manage departments, reporting lines, role ownership, and approval chains',
        ]);
    }

    private function loadDepartmentAssignments(): void
    {
        $this->departmentManagers = Department::query()
            ->get(['id', 'manager_user_id'])
            ->mapWithKeys(fn (Department $department): array => [
                $department->id => $department->manager_user_id ? (string) $department->manager_user_id : '',
            ])
            ->all();
    }

    private function loadUserAssignments(): void
    {
        $this->userAssignments = User::query()
            ->get(['id', 'role', 'department_id', 'reports_to_user_id', 'is_active'])
            ->mapWithKeys(fn (User $user): array => [
                $user->id => [
                    'role' => $user->role,
                    'department_id' => (string) $user->department_id,
                    'reports_to_user_id' => $user->reports_to_user_id ? (string) $user->reports_to_user_id : '',
                    'is_active' => (bool) $user->is_active,
                ],
            ])
            ->all();
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
            throw new AuthorizationException('Only owner can manage organization hierarchy settings.');
        }
    }

    private function defaultStepKeyForApproverSource(string $source, ?string $value): string
    {
        return match ($source) {
            'reports_to' => 'direct_manager_review',
            'department_manager' => 'department_head_review',
            'role' => 'role_'.strtolower((string) $value).'_review',
            'user' => 'specific_user_'.(string) $value.'_review',
            default => 'approval_step_review',
        };
    }

    private function applyDefaultLabelsToWorkflowSteps(ApprovalWorkflow $workflow): void
    {
        $workflow->load(['steps' => fn ($query) => $query->where('is_active', true)->orderBy('step_order')]);

        foreach ($workflow->steps as $step) {
            if ($step->step_key) {
                continue;
            }

            $step->forceFill([
                'step_key' => $this->defaultStepKeyForApproverSource(
                    (string) $step->actor_type,
                    $step->actor_value ? (string) $step->actor_value : null
                ),
            ])->save();
        }
    }

    private function removeDuplicatePresetStepTypes(ApprovalWorkflow $workflow): void
    {
        $workflow->load(['steps' => fn ($query) => $query->where('is_active', true)->orderBy('step_order')]);

        $reportsToSteps = $workflow->steps
            ->filter(fn ($step): bool => $step->actor_type === 'reports_to')
            ->values();
        if ($reportsToSteps->count() > 1) {
            $reportsToSteps->slice(1)->each(fn ($step) => $step->delete());
        }

        $financeRoleSteps = $workflow->steps
            ->filter(fn ($step): bool => $step->actor_type === 'role' && $step->actor_value === UserRole::Finance->value)
            ->values();
        if ($financeRoleSteps->count() > 1) {
            $financeRoleSteps->slice(1)->each(fn ($step) => $step->delete());
        }
    }

    private function normalizeStepOrdersAndLabels(): void
    {
        app(ApprovalWorkflowStepOrderService::class)
            ->normalizeCompanyRequestWorkflows((int) auth()->user()->company_id);
    }
}
