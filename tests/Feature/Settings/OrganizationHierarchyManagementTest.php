<?php

namespace Tests\Feature\Settings;

use App\Actions\Approvals\AddApprovalWorkflowStep;
use App\Actions\Approvals\CreateApprovalWorkflow;
use App\Actions\Approvals\DeleteApprovalWorkflow;
use App\Actions\Approvals\SetApprovalWorkflowDefault;
use App\Actions\Company\CreateCompanyUser;
use App\Actions\Company\CreateDepartment;
use App\Actions\Company\UpdateCompanyUserAssignment;
use App\Domains\Approvals\Models\ApprovalWorkflow;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Enums\UserRole;
use App\Livewire\Settings\OrganizationHierarchyPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationHierarchyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_hierarchy_assignments_and_workflow_steps(): void
    {
        [$company, $generalDepartment] = $this->createCompanyContext('Org Hierarchy A');
        $owner = $this->createUser($company, $generalDepartment, UserRole::Owner->value);

        $operationsDepartment = app(CreateDepartment::class)($owner, [
            'name' => 'Operations',
            'code' => 'OPS',
            'manager_user_id' => null,
        ]);

        $financeUser = app(CreateCompanyUser::class)($owner, [
            'name' => 'Finance Officer',
            'email' => 'finance-officer@example.test',
            'phone' => '08000000000',
            'gender' => 'male',
            'password' => 'password123',
            'role' => UserRole::Finance->value,
            'department_id' => $operationsDepartment->id,
            'reports_to_user_id' => $owner->id,
        ]);

        app(UpdateCompanyUserAssignment::class)($owner, $financeUser, [
            'role' => UserRole::Finance->value,
            'department_id' => $generalDepartment->id,
            'reports_to_user_id' => $owner->id,
            'is_active' => true,
        ]);

        $workflow = app(CreateApprovalWorkflow::class)($owner, [
            'name' => 'Standard Approval Chain',
            'code' => null,
            'description' => 'Hierarchy and finance chain',
            'is_default' => true,
            'applies_to' => 'request',
        ]);

        app(AddApprovalWorkflowStep::class)($owner, $workflow, [
            'actor_type' => 'reports_to',
            'actor_value' => null,
            'step_key' => 'line_manager',
        ]);

        app(AddApprovalWorkflowStep::class)($owner, $workflow, [
            'actor_type' => 'role',
            'actor_value' => UserRole::Finance->value,
            'step_key' => 'finance_signoff',
        ]);

        $this->assertDatabaseHas('departments', [
            'id' => $operationsDepartment->id,
            'company_id' => $company->id,
            'name' => 'Operations',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $financeUser->id,
            'company_id' => $company->id,
            'department_id' => $generalDepartment->id,
            'role' => UserRole::Finance->value,
            'reports_to_user_id' => $owner->id,
        ]);

        $this->assertDatabaseHas('approval_workflows', [
            'id' => $workflow->id,
            'company_id' => $company->id,
            'code' => 'standard_approval_chain',
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('approval_workflow_steps', [
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'actor_type' => 'reports_to',
        ]);

        $this->assertDatabaseHas('approval_workflow_steps', [
            'workflow_id' => $workflow->id,
            'step_order' => 2,
            'actor_type' => 'role',
            'actor_value' => UserRole::Finance->value,
        ]);
    }

    public function test_owner_can_switch_default_workflow(): void
    {
        [$company, $generalDepartment] = $this->createCompanyContext('Org Hierarchy B');
        $owner = $this->createUser($company, $generalDepartment, UserRole::Owner->value);

        $workflowA = app(CreateApprovalWorkflow::class)($owner, [
            'name' => 'Workflow A',
            'code' => 'wf_a',
            'is_default' => true,
            'applies_to' => 'request',
        ]);

        $workflowB = app(CreateApprovalWorkflow::class)($owner, [
            'name' => 'Workflow B',
            'code' => 'wf_b',
            'is_default' => false,
            'applies_to' => 'request',
        ]);

        app(SetApprovalWorkflowDefault::class)($owner, $workflowB);

        $this->assertDatabaseHas('approval_workflows', [
            'id' => $workflowA->id,
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('approval_workflows', [
            'id' => $workflowB->id,
            'is_default' => true,
        ]);
    }

    public function test_non_owner_cannot_access_organization_settings_page(): void
    {
        [$company, $generalDepartment] = $this->createCompanyContext('Org Hierarchy C');
        $finance = $this->createUser($company, $generalDepartment, UserRole::Finance->value);

        $this->actingAs($finance)
            ->get(route('settings.organization'))
            ->assertForbidden();
    }

    public function test_owner_can_create_preset_workflow_in_one_click(): void
    {
        [$company, $generalDepartment] = $this->createCompanyContext('Org Hierarchy D');
        $owner = $this->createUser($company, $generalDepartment, UserRole::Owner->value);

        $this->actingAs($owner);

        Livewire::test(OrganizationHierarchyPage::class)
            ->call('createPresetWorkflow')
            ->assertHasNoErrors();

        $workflow = ApprovalWorkflow::query()
            ->where('company_id', $company->id)
            ->where('code', 'preset_standard_request_2step')
            ->first();

        $this->assertNotNull($workflow);

        $this->assertDatabaseHas('approval_workflows', [
            'company_id' => $company->id,
            'code' => 'preset_standard_request_2step',
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('approval_workflow_steps', [
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'actor_type' => 'reports_to',
        ]);

        $this->assertDatabaseHas('approval_workflow_steps', [
            'workflow_id' => $workflow->id,
            'step_order' => 2,
            'actor_type' => 'role',
            'actor_value' => UserRole::Finance->value,
        ]);
    }

    public function test_owner_can_delete_secondary_workflow_but_not_last_active_workflow(): void
    {
        [$company, $generalDepartment] = $this->createCompanyContext('Org Hierarchy E');
        $owner = $this->createUser($company, $generalDepartment, UserRole::Owner->value);

        $defaultWorkflow = app(CreateApprovalWorkflow::class)($owner, [
            'name' => 'Workflow Default',
            'code' => 'workflow_default',
            'is_default' => true,
            'applies_to' => 'request',
        ]);

        $secondaryWorkflow = app(CreateApprovalWorkflow::class)($owner, [
            'name' => 'Workflow Secondary',
            'code' => 'workflow_secondary',
            'is_default' => false,
            'applies_to' => 'request',
        ]);

        app(DeleteApprovalWorkflow::class)($owner, $secondaryWorkflow);

        $this->assertSoftDeleted('approval_workflows', [
            'id' => $secondaryWorkflow->id,
        ]);

        try {
            app(DeleteApprovalWorkflow::class)($owner, $defaultWorkflow->fresh());
            $this->fail('Expected validation exception for deleting last active workflow.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('workflow', $exception->errors());
        }
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+company@example.test',
            'is_active' => true,
        ]);

        $department = Department::query()->create([
            'company_id' => $company->id,
            'name' => 'General',
            'code' => 'GEN',
            'is_active' => true,
        ]);

        return [$company, $department];
    }

    private function createUser(Company $company, Department $department, string $role): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
