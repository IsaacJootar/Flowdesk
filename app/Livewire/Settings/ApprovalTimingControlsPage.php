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
#[Title('Approval Deadline Controls')]
class ApprovalTimingControlsPage extends Component
{
    // Feedback messaging
    public ?string $feedbackMessage = null;           // Success message to display to user
    public ?string $feedbackError = null;             // Error message to display to user
    public int $feedbackKey = 0;                      // Key to trigger feedback re-render

    // Organization-level approval timing defaults (in hours)
    public string $org_step_due_hours = '';           // Default hours until approval step is due
    public string $org_reminder_hours_before_due = '';// Default hours before due date to send reminder
    public string $org_escalation_grace_hours = '';   // Default grace period before escalation

    // Override modal state
    public bool $showOverrideModal = false;           // Whether override modal is visible
    public ?int $editingDepartmentId = null;          // Department currently being edited, null if creating new

    /**
     * Department override form data.
     * Stores timing overrides for specific departments that differ from organization defaults.
     *
     * @var array{department_id:string, step_due_hours:string, reminder_hours_before_due:string, escalation_grace_hours:string}
     */
    public array $overrideForm = [
        'department_id' => '',
        'step_due_hours' => '',
        'reminder_hours_before_due' => '',
        'escalation_grace_hours' => '',
    ];

    /**
     * Initialize component and load organization-level approval timing defaults.
     * Authorization check ensures only company owners can access this page.
     */
    public function mount(ApprovalTimingPolicyResolver $resolver): void
    {
        $this->authorizeOwner();
        $this->hydrateOrganizationDefaults($resolver);
    }

    /**
     * Save organization-level approval timing defaults.
     * Updates timing values for all departments that don't have specific overrides.
     */
    public function saveOrganizationDefaults(ApprovalTimingPolicyResolver $resolver): void
    {
        $this->authorizeOwner();
        $this->feedbackError = null;

        // Validate input values against configured min/max constraints
        $validated = $this->validate([
            'org_step_due_hours' => ['required', 'integer', 'min:'.ApprovalTimingPolicyResolver::MIN_STEP_DUE_HOURS, 'max:'.ApprovalTimingPolicyResolver::MAX_STEP_DUE_HOURS],
            'org_reminder_hours_before_due' => ['required', 'integer', 'min:'.ApprovalTimingPolicyResolver::MIN_REMINDER_HOURS_BEFORE_DUE],
            'org_escalation_grace_hours' => ['required', 'integer', 'min:0', 'max:'.ApprovalTimingPolicyResolver::MAX_ESCALATION_GRACE_HOURS],
        ]);

        // Apply guardrail policy to ensure values are within acceptable ranges and logically consistent
        $normalized = $resolver->guardrail([
            'step_due_hours' => (int) $validated['org_step_due_hours'],
            'reminder_hours_before_due' => (int) $validated['org_reminder_hours_before_due'],
            'escalation_grace_hours' => (int) $validated['org_escalation_grace_hours'],
        ]);

        // Retrieve and update the company's approval timing settings
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

    /**
     * Open modal for creating a new department override.
     * Pre-populates form with organization defaults as starting values.
     */
    public function openCreateOverrideModal(ApprovalTimingPolicyResolver $resolver): void
    {
        $this->authorizeOwner();

        // Fetch current organization defaults to use as form defaults
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

    /**
     * Open modal for editing an existing department override.
     * Loads the override data into the form.
     */
    public function openEditOverrideModal(int $departmentId): void
    {
        $this->authorizeOwner();

        // Fetch the existing override record for this department
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

    /**
     * Close the override modal without saving.
     */
    public function closeOverrideModal(): void
    {
        $this->showOverrideModal = false;
    }

    /**
     * Save or create a department-specific approval timing override.
     * Allows departments to have different timing rules than the organization default.
     */
    public function saveDepartmentOverride(ApprovalTimingPolicyResolver $resolver): void
    {
        $this->authorizeOwner();
        $this->feedbackError = null;

        // Validate override form data with same constraints as organization defaults
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

        // Apply guardrail policy to ensure override values are valid
        $normalized = $resolver->guardrail([
            'step_due_hours' => (int) $validated['overrideForm']['step_due_hours'],
            'reminder_hours_before_due' => (int) $validated['overrideForm']['reminder_hours_before_due'],
            'escalation_grace_hours' => (int) $validated['overrideForm']['escalation_grace_hours'],
        ]);

        // Create new override or update existing one using updateOrCreate to handle both cases
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

    /**
     * Remove a department override, reverting it to inherit organization defaults.
     */
    public function removeDepartmentOverride(int $departmentId): void
    {
        $this->authorizeOwner();

        // Delete the override record for this department
        $this->overrideQuery()
            ->where('department_id', $departmentId)
            ->delete();

        $this->setFeedback('Department timing override removed. Department now inherits organization defaults.');
    }

    /**
     * Render the approval timing controls settings page.
     * Provides departments list, active overrides, and effective organization timing.
     */
    public function render(ApprovalTimingPolicyResolver $resolver): View
    {
        $companyId = (int) \Illuminate\Support\Facades\Auth::user()->company_id;

        // Fetch all active departments for override selection
        $departments = Department::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Fetch all department overrides with their department names
        $overrides = $this->overrideQuery()
            ->with('department:id,name')
            ->orderBy('department_id')
            ->get();

        // Resolve effective organization-level timing (after any guardrail adjustments)
        $orgEffective = $resolver->resolve($companyId, null);

        return view('livewire.settings.approval-timing-controls-page', [
            'departments' => $departments,
            'overrides' => $overrides,
            'orgEffective' => $orgEffective,
        ]);
    }

    /**
     * Load organization-level approval timing defaults into component properties.
     * Called during component initialization.
     */
    private function hydrateOrganizationDefaults(ApprovalTimingPolicyResolver $resolver): void
    {
        // Fetch company settings for the current user's organization
        $settings = $resolver->settingsForCompany((int) \Illuminate\Support\Facades\Auth::user()->company_id);

        $this->org_step_due_hours = (string) ((int) $settings->step_due_hours);
        $this->org_reminder_hours_before_due = (string) ((int) $settings->reminder_hours_before_due);
        $this->org_escalation_grace_hours = (string) ((int) $settings->escalation_grace_hours);
    }

    /**
     * Build base query for department overrides scoped to current company.
     * Ensures separation of data across multi-tenant system.
     */
    private function overrideQuery()
    {
        return DepartmentApprovalTimingOverride::query()
            ->where('company_id', (int) \Illuminate\Support\Facades\Auth::user()->company_id);
    }

    /**
     * Set feedback message to display to user.
     * Clears opposite message type (error -> success, success -> error).
     * Increments feedbackKey to trigger UI refresh.
     */
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

    /**
     * Ensure only company owners can access approval timing controls.
     * Throws AuthorizationException if user is not authenticated or not an owner.
     */
    private function authorizeOwner(): void
    {
        if (! \Illuminate\Support\Facades\Auth::check() || \Illuminate\Support\Facades\Auth::user()->role !== UserRole::Owner->value) {
            throw new AuthorizationException('Only admin (owner) can manage approval timing controls.');
        }
    }
}
