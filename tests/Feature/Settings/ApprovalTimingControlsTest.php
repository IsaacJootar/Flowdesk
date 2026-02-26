<?php

namespace Tests\Feature\Settings;

use App\Actions\Requests\SubmitSpendRequest;
use App\Domains\Approvals\Models\ApprovalWorkflow;
use App\Domains\Approvals\Models\ApprovalWorkflowStep;
use App\Domains\Approvals\Models\DepartmentApprovalTimingOverride;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\ApprovalTimingPolicyResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApprovalTimingControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_access_approval_timing_controls_page(): void
    {
        [$company, $department] = $this->createCompanyContext('Timing Controls Owner');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $this->actingAs($owner)
            ->get(route('settings.approval-timing-controls'))
            ->assertOk();
    }

    public function test_staff_cannot_access_approval_timing_controls_page(): void
    {
        [$company, $department] = $this->createCompanyContext('Timing Controls Staff');
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $this->actingAs($staff)
            ->get(route('settings.approval-timing-controls'))
            ->assertForbidden();
    }

    public function test_department_override_takes_precedence_over_org_default(): void
    {
        [$company, $department] = $this->createCompanyContext('Timing Controls Precedence');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $resolver = app(ApprovalTimingPolicyResolver::class);

        $this->actingAs($owner);
        $settings = $resolver->settingsForCompany($company->id);
        $settings->forceFill([
            'step_due_hours' => 24,
            'reminder_hours_before_due' => 6,
            'escalation_grace_hours' => 6,
            'updated_by' => $owner->id,
        ])->save();

        DepartmentApprovalTimingOverride::query()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'step_due_hours' => 36,
            'reminder_hours_before_due' => 8,
            'escalation_grace_hours' => 4,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $resolved = $resolver->resolve($company->id, $department->id);
        $this->assertSame(36, (int) $resolved['step_due_hours']);
        $this->assertSame(8, (int) $resolved['reminder_hours_before_due']);
        $this->assertSame(4, (int) $resolved['escalation_grace_hours']);
        $this->assertSame('department', (string) $resolved['source']);
    }

    public function test_submit_request_uses_department_timing_override_for_due_at(): void
    {
        [$company, $department] = $this->createCompanyContext('Timing Controls Submit');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value, [
            'reports_to_user_id' => $owner->id,
        ]);

        DepartmentApprovalTimingOverride::query()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'step_due_hours' => 48,
            'reminder_hours_before_due' => 12,
            'escalation_grace_hours' => 8,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $workflow = ApprovalWorkflow::query()->create([
            'company_id' => $company->id,
            'name' => 'Default Request Workflow',
            'code' => 'default_request_'.Str::lower(Str::random(4)),
            'applies_to' => 'request',
            'is_active' => true,
            'is_default' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        ApprovalWorkflowStep::query()->create([
            'company_id' => $company->id,
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'step_key' => 'owner_approval',
            'actor_type' => 'role',
            'actor_value' => UserRole::Owner->value,
            'notification_channels' => ['in_app'],
            'is_active' => true,
        ]);

        $request = SpendRequest::query()->create([
            'company_id' => $company->id,
            'request_code' => 'FD-REQ-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'requested_by' => $staff->id,
            'department_id' => $department->id,
            'workflow_id' => $workflow->id,
            'title' => 'Timing override request',
            'amount' => 150000,
            'currency' => 'NGN',
            'status' => 'draft',
            'paid_amount' => 0,
            'metadata' => [
                'type' => 'spend',
                'request_type_code' => 'spend',
            ],
        ]);

        $this->actingAs($staff);
        $submitted = app(SubmitSpendRequest::class)($staff, $request, null);

        $approval = $submitted->approvals()->where('step_order', 1)->first();
        $this->assertNotNull($approval);

        $metadata = (array) ($approval->metadata ?? []);
        $sla = (array) ($metadata['sla'] ?? []);
        $this->assertSame(48, (int) ($sla['step_due_hours'] ?? 0));
        $this->assertSame(12, (int) ($sla['reminder_hours_before_due'] ?? 0));
        $this->assertSame(8, (int) ($sla['escalation_grace_hours'] ?? 0));

        $hoursUntilDue = Carbon::now()->diffInHours($approval->due_at, false);
        $this->assertGreaterThanOrEqual(47, $hoursUntilDue);
        $this->assertLessThanOrEqual(49, $hoursUntilDue);
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
}
