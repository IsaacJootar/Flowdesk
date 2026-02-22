<?php

namespace Tests\Feature\Requests;

use App\Domains\Approvals\Models\ApprovalWorkflow;
use App\Domains\Approvals\Models\ApprovalWorkflowStep;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class RequestApprovalHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_to_hierarchy_step_controls_approver(): void
    {
        [$company, $department] = $this->createCompanyContext('Req Hierarchy ReportsTo');
        $manager = $this->createUser($company, $department, UserRole::Manager->value);
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value, [
            'reports_to_user_id' => $manager->id,
        ]);

        $workflow = $this->createDefaultRequestWorkflow($company);
        $this->createWorkflowStep($company, $workflow, 1, 'reports_to');
        $request = $this->createSpendRequest($company, $department, $staff, $workflow, 1);

        $this->assertTrue(Gate::forUser($manager)->allows('approve', $request));
        $this->assertFalse(Gate::forUser($finance)->allows('approve', $request));
    }

    public function test_department_head_step_uses_department_manager_assignment(): void
    {
        [$company, $department] = $this->createCompanyContext('Req Hierarchy DeptHead');
        $departmentManager = $this->createUser($company, $department, UserRole::Manager->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $department->update(['manager_user_id' => $departmentManager->id]);

        $workflow = $this->createDefaultRequestWorkflow($company);
        $this->createWorkflowStep($company, $workflow, 1, 'department_manager');
        $request = $this->createSpendRequest($company, $department, $staff, $workflow, 1);

        $this->assertTrue(Gate::forUser($departmentManager)->allows('approve', $request));
    }

    public function test_role_step_enforces_configured_role_ownership(): void
    {
        [$company, $department] = $this->createCompanyContext('Req Hierarchy Role');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value, [
            'reports_to_user_id' => $owner->id,
        ]);

        $workflow = $this->createDefaultRequestWorkflow($company);
        $this->createWorkflowStep($company, $workflow, 1, 'role', UserRole::Finance->value);
        $request = $this->createSpendRequest($company, $department, $staff, $workflow, 1);

        $this->assertTrue(Gate::forUser($finance)->allows('approve', $request));
        $this->assertFalse(Gate::forUser($owner)->allows('approve', $request));
    }

    public function test_policy_falls_back_to_default_role_logic_without_workflow_configuration(): void
    {
        [$company, $department] = $this->createCompanyContext('Req Hierarchy Fallback');
        $manager = $this->createUser($company, $department, UserRole::Manager->value);
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value, [
            'reports_to_user_id' => $manager->id,
        ]);

        $request = $this->createSpendRequest($company, $department, $staff, null, 1);

        $this->assertTrue(Gate::forUser($manager)->allows('approve', $request));
        $this->assertTrue(Gate::forUser($finance)->allows('approve', $request));
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createUser(Company $company, Department $department, string $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'role' => $role,
            'is_active' => true,
            'reports_to_user_id' => null,
        ], $overrides));
    }

    private function createDefaultRequestWorkflow(Company $company): ApprovalWorkflow
    {
        return ApprovalWorkflow::query()->create([
            'company_id' => $company->id,
            'name' => 'Default Request Workflow',
            'code' => 'default_request',
            'applies_to' => 'request',
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    private function createWorkflowStep(
        Company $company,
        ApprovalWorkflow $workflow,
        int $stepOrder,
        string $actorType,
        ?string $actorValue = null
    ): ApprovalWorkflowStep {
        return ApprovalWorkflowStep::query()->create([
            'company_id' => $company->id,
            'workflow_id' => $workflow->id,
            'step_order' => $stepOrder,
            'step_key' => 'step_'.$stepOrder,
            'actor_type' => $actorType,
            'actor_value' => $actorValue,
            'is_active' => true,
        ]);
    }

    private function createSpendRequest(
        Company $company,
        Department $department,
        User $requester,
        ?ApprovalWorkflow $workflow,
        int $currentStep
    ): SpendRequest {
        return SpendRequest::query()->create([
            'company_id' => $company->id,
            'request_code' => 'FD-REQ-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'requested_by' => $requester->id,
            'department_id' => $department->id,
            'vendor_id' => null,
            'workflow_id' => $workflow?->id,
            'title' => 'Operational request',
            'description' => 'Seeded request',
            'amount' => 250000,
            'currency' => 'NGN',
            'status' => 'pending',
            'approved_amount' => null,
            'paid_amount' => 0,
            'current_approval_step' => $currentStep,
            'submitted_at' => now(),
            'metadata' => null,
        ]);
    }
}

