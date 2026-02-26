<?php

namespace App\Livewire\Settings;

use App\Domains\Approvals\Models\DepartmentApprovalTimingOverride;
use App\Domains\Company\Models\Department;
use App\Enums\UserRole;
use App\Services\ApprovalTimingPolicyResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Approval Timing Controls')]
class ApprovalTimingControlsPage extends Component
{
    public ?string $feedbackMessage = null;

    public ?string $feedbackError = null;

    public int $feedbackKey = 0;

    public string $org_step_due_hours = '';

    public string $org_reminder_hours_before_due = '';

    public string $org_escalation_grace_hours = '';

    public bool $showOverrideModal = false;

    public ?int $editingDepartmentId = null;

    /**
     * @var array{department_id:string, step_due_hours:string, reminder_hours_before_due:string, escalation_grace_hours:string}
     */
    public array $overrideForm = [
        'department_id' => '',
        'step_due_hours' => '',
        'reminder_hours_before_due' => '',
        'escalation_grace_hours' => '',
    ];

    public function mount(ApprovalTimingPolicyResolver $resolver): void
    {
        $this->authorizeOwner();
        $this->hydrateOrganizationDefaults($resolver);
    }

    public function saveOrganizationDefaults(ApprovalTimingPolicyResolver $resolver): void
    {
        $this->authorizeOwner();
        $this->feedbackError = null;

        $validated = $this->validate([
            'org_step_due_hours' => ['required', 'integer', 'min:'.ApprovalTimingPolicyResolver::MIN_STEP_DUE_HOURS, 'max:'.ApprovalTimingPolicyResolver::MAX_STEP_DUE_HOURS],
            'org_reminder_hours_before_due' => ['required', 'integer', 'min:'.ApprovalTimingPolicyResolver::MIN_REMINDER_HOURS_BEFORE_DUE],
            'org_escalation_grace_hours' => ['required', 'integer', 'min:0', 'max:'.ApprovalTimingPolicyResolver::MAX_ESCALATION_GRACE_HOURS],
        ]);

        $normalized = $resolver->guardrail([
            'step_due_hours' => (int) $validated['org_step_due_hours'],
            'reminder_hours_before_due' => (int) $validated['org_reminder_hours_before_due'],
            'escalation_grace_hours' => (int) $validated['org_escalation_grace_hours'],
        ]);

        $setting = $resolver->settingsForCompany((int) \Illuminate\Support\Facades\Auth::user()->company_id);
        $setting->forceFill([
            'step_due_hours' => $normalized['step_due_hours'],
            'reminder_hours_before_due' => $normalized['reminder_hours_before_due'],
            'escalation_grace_hours' => $normalized['escalation_grace_hours'],
            'updated_by' => \Illuminate\Support\Facades\Auth::id(),
        ])->save();

        $this->org_step_due_hours = (string) $normalized['step_due_hours'];
        $this->org_reminder_hours_before_due = (string) $normalized['reminder_hours_before_due'];
        $this->org_escalation_grace_hours = (string) $normalized['escalation_grace_hours'];

        $this->setFeedback('Organization approval timing defaults updated.');
    }

    public function openCreateOverrideModal(ApprovalTimingPolicyResolver $resolver): void
    {
        $this->authorizeOwner();

        $defaults = $resolver->resolve((int) \Illuminate\Support\Facades\Auth::user()->company_id, null);
        $this->editingDepartmentId = null;
        $this->overrideForm = [
            'department_id' => '',
            'step_due_hours' => (string) $defaults['step_due_hours'],
            'reminder_hours_before_due' => (string) $defaults['reminder_hours_before_due'],
            'escalation_grace_hours' => (string) $defaults['escalation_grace_hours'],
        ];
        $this->resetErrorBag();
        $this->showOverrideModal = true;
    }

    public function openEditOverrideModal(int $departmentId): void
    {
        $this->authorizeOwner();

        $override = $this->overrideQuery()
            ->where('department_id', $departmentId)
            ->first();

        if (! $override) {
            $this->setFeedback('Department override not found.', true);

            return;
        }

        $this->editingDepartmentId = (int) $override->department_id;
        $this->overrideForm = [
            'department_id' => (string) $override->department_id,
            'step_due_hours' => (string) $override->step_due_hours,
            'reminder_hours_before_due' => (string) $override->reminder_hours_before_due,
            'escalation_grace_hours' => (string) $override->escalation_grace_hours,
        ];
        $this->resetErrorBag();
        $this->showOverrideModal = true;
    }

    public function closeOverrideModal(): void
    {
        $this->showOverrideModal = false;
    }

    public function saveDepartmentOverride(ApprovalTimingPolicyResolver $resolver): void
    {
        $this->authorizeOwner();
        $this->feedbackError = null;

        $validated = $this->validate([
            'overrideForm.department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')->where(
                    fn ($query) => $query
                        ->where('company_id', (int) \Illuminate\Support\Facades\Auth::user()->company_id)
                        ->whereNull('deleted_at')
                ),
            ],
            'overrideForm.step_due_hours' => ['required', 'integer', 'min:'.ApprovalTimingPolicyResolver::MIN_STEP_DUE_HOURS, 'max:'.ApprovalTimingPolicyResolver::MAX_STEP_DUE_HOURS],
            'overrideForm.reminder_hours_before_due' => ['required', 'integer', 'min:'.ApprovalTimingPolicyResolver::MIN_REMINDER_HOURS_BEFORE_DUE],
            'overrideForm.escalation_grace_hours' => ['required', 'integer', 'min:0', 'max:'.ApprovalTimingPolicyResolver::MAX_ESCALATION_GRACE_HOURS],
        ]);

        $departmentId = (int) $validated['overrideForm']['department_id'];
        $normalized = $resolver->guardrail([
            'step_due_hours' => (int) $validated['overrideForm']['step_due_hours'],
            'reminder_hours_before_due' => (int) $validated['overrideForm']['reminder_hours_before_due'],
            'escalation_grace_hours' => (int) $validated['overrideForm']['escalation_grace_hours'],
        ]);

        $this->overrideQuery()->updateOrCreate(
            ['department_id' => $departmentId],
            [
                'step_due_hours' => $normalized['step_due_hours'],
                'reminder_hours_before_due' => $normalized['reminder_hours_before_due'],
                'escalation_grace_hours' => $normalized['escalation_grace_hours'],
                'updated_by' => \Illuminate\Support\Facades\Auth::id(),
                'created_by' => \Illuminate\Support\Facades\Auth::id(),
            ]
        );

        $this->showOverrideModal = false;
        $this->setFeedback('Department timing override saved.');
    }

    public function removeDepartmentOverride(int $departmentId): void
    {
        $this->authorizeOwner();

        $this->overrideQuery()
            ->where('department_id', $departmentId)
            ->delete();

        $this->setFeedback('Department timing override removed. Department now inherits organization defaults.');
    }

    public function render(ApprovalTimingPolicyResolver $resolver): View
    {
        $companyId = (int) \Illuminate\Support\Facades\Auth::user()->company_id;
        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $overrides = $this->overrideQuery()
            ->with('department:id,name')
            ->orderBy('department_id')
            ->get();

        $orgEffective = $resolver->resolve($companyId, null);

        return view('livewire.settings.approval-timing-controls-page', [
            'departments' => $departments,
            'overrides' => $overrides,
            'orgEffective' => $orgEffective,
        ]);
    }

    private function hydrateOrganizationDefaults(ApprovalTimingPolicyResolver $resolver): void
    {
        $settings = $resolver->settingsForCompany((int) \Illuminate\Support\Facades\Auth::user()->company_id);

        $this->org_step_due_hours = (string) ((int) $settings->step_due_hours);
        $this->org_reminder_hours_before_due = (string) ((int) $settings->reminder_hours_before_due);
        $this->org_escalation_grace_hours = (string) ((int) $settings->escalation_grace_hours);
    }

    private function overrideQuery()
    {
        return DepartmentApprovalTimingOverride::query()
            ->where('company_id', (int) \Illuminate\Support\Facades\Auth::user()->company_id);
    }

    private function setFeedback(string $message, bool $isError = false): void
    {
        if ($isError) {
            $this->feedbackError = $message;
            $this->feedbackMessage = null;
        } else {
            $this->feedbackMessage = $message;
            $this->feedbackError = null;
        }

        $this->feedbackKey++;
    }

    private function authorizeOwner(): void
    {
        if (! \Illuminate\Support\Facades\Auth::check() || \Illuminate\Support\Facades\Auth::user()->role !== UserRole::Owner->value) {
            throw new AuthorizationException('Only admin (owner) can manage approval timing controls.');
        }
    }
}
