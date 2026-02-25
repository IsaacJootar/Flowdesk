<?php

namespace Tests\Feature\Requests;

use App\Actions\Requests\SubmitSpendRequest;
use App\Actions\Requests\DecideSpendRequest;
use App\Actions\Requests\UploadRequestAttachment;
use App\Domains\Approvals\Models\ApprovalWorkflow;
use App\Domains\Approvals\Models\ApprovalWorkflowStep;
use App\Domains\Approvals\Models\RequestApproval;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyCommunicationSetting;
use App\Domains\Company\Models\Department;
use App\Domains\Budgets\Models\DepartmentBudget;
use App\Domains\Requests\Models\CompanyRequestType;
use App\Domains\Requests\Models\CompanyRequestPolicySetting;
use App\Domains\Requests\Models\RequestCommunicationLog;
use App\Domains\Requests\Models\SpendRequest;
use App\Domains\Expenses\Models\Expense;
use App\Enums\UserRole;
use App\Livewire\Requests\RequestCommunicationsPage;
use App\Livewire\Requests\RequestReportsPage;
use App\Livewire\Requests\RequestsPage;
use App\Models\User;
use App\Services\RequestApprovalSlaProcessor;
use App\Services\RequestCommunicationRetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class RequestApprovalAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_routes_are_accessible_for_company_user(): void
    {
        [$company, $department] = $this->createCompanyContext('Request Route Access');
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $this->actingAs($staff);

        $this->get(route('requests.index'))->assertOk();
        $this->get(route('requests.communications'))->assertOk();
        $this->get(route('requests.reports'))->assertOk();
    }

    public function test_inactive_user_cannot_access_request_routes(): void
    {
        [$company, $department] = $this->createCompanyContext('Request Inactive Access');
        $inactiveUser = $this->createUser($company, $department, UserRole::Staff->value, [
            'is_active' => false,
        ]);

        $this->actingAs($inactiveUser);

        $this->get(route('requests.index'))->assertForbidden();
        $this->get(route('requests.communications'))->assertForbidden();
        $this->get(route('requests.reports'))->assertForbidden();
    }

    public function test_request_reports_average_decision_time_never_shows_negative_hours(): void
    {
        [$company, $department] = $this->createCompanyContext('Request Reports Avg Decision Clamp');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $this->createRequest($company, $department, $staff, null, [
            'request_code' => 'FD-REQ-RPT-NEG-001',
            'status' => 'approved',
            'submitted_at' => now(),
            'decided_at' => now()->subHours(3),
        ]);

        $this->actingAs($owner);

        Livewire::test(RequestReportsPage::class)
            ->call('loadData')
            ->assertSee('Avg Decision Time')
            ->assertSee('0.0h')
            ->assertDontSee('-3.0h');
    }

    public function test_request_reports_row_cycle_time_never_shows_negative_hours(): void
    {
        [$company, $department] = $this->createCompanyContext('Request Reports Row Cycle Clamp');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $this->createRequest($company, $department, $staff, null, [
            'request_code' => 'FD-REQ-RPT-ROW-NEG-001',
            'title' => 'Negative Cycle Row Check',
            'status' => 'approved',
            'submitted_at' => now(),
            'decided_at' => now()->subHours(2)->subMinutes(30),
        ]);

        $this->actingAs($owner);

        Livewire::test(RequestReportsPage::class)
            ->call('loadData')
            ->assertSee('Negative Cycle Row Check')
            ->assertSee('0.0h')
            ->assertDontSee('-2.5h');
    }

    public function test_requests_page_scope_filters_pending_and_decided_by_me(): void
    {
        [$company, $department] = $this->createCompanyContext('Request Scope Filters');
        $manager = $this->createUser($company, $department, UserRole::Manager->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value, [
            'reports_to_user_id' => $manager->id,
        ]);

        $workflow = $this->createDefaultRequestWorkflow($company, $manager);
        $step = $this->createWorkflowStep($company, $workflow, 1, 'role', UserRole::Manager->value);

        $pendingRequest = $this->createRequest($company, $department, $staff, $workflow, [
            'request_code' => 'FD-REQ-PENDING-001',
            'status' => 'in_review',
            'current_approval_step' => 1,
        ]);
        $this->createPendingApproval($company, $pendingRequest, $step);

        $decidedRequest = $this->createRequest($company, $department, $staff, $workflow, [
            'request_code' => 'FD-REQ-DECIDED-001',
            'status' => 'approved',
            'current_approval_step' => null,
            'decided_at' => now(),
        ]);
        RequestApproval::query()->create([
            'company_id' => $company->id,
            'request_id' => $decidedRequest->id,
            'workflow_step_id' => $step->id,
            'step_order' => 1,
            'step_key' => 'manager_approval',
            'status' => 'approved',
            'action' => 'approve',
            'acted_by' => $manager->id,
            'acted_at' => now()->subMinute(),
            'comment' => 'Approved',
            'metadata' => [
                'actor_type' => 'role',
                'actor_value' => UserRole::Manager->value,
            ],
        ]);

        $this->actingAs($manager);

        Livewire::test(RequestsPage::class)
            ->call('loadData')
            ->set('scopeFilter', 'pending_my_approval')
            ->assertSee('FD-REQ-PENDING-001')
            ->assertDontSee('FD-REQ-DECIDED-001')
            ->set('scopeFilter', 'decided_by_me')
            ->assertSee('FD-REQ-DECIDED-001')
            ->assertDontSee('FD-REQ-PENDING-001');
    }

    public function test_submit_uses_only_amount_applicable_workflow_steps(): void
    {
        [$company, $department] = $this->createCompanyContext('Request Amount Range Submit');
        $manager = $this->createUser($company, $department, UserRole::Manager->value);
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value, [
            'reports_to_user_id' => $manager->id,
        ]);

        $workflow = $this->createDefaultRequestWorkflow($company, $manager);
        $this->createWorkflowStep($company, $workflow, 1, 'reports_to', null, ['in_app'], null, 100000);
        $this->createWorkflowStep($company, $workflow, 2, 'role', UserRole::Finance->value, ['in_app'], 100001, null);

        $request = $this->createRequest($company, $department, $staff, $workflow, [
            'request_code' => 'FD-REQ-RANGE-001',
            'status' => 'draft',
            'amount' => 150000,
            'metadata' => [
                'type' => 'spend',
                'request_type_code' => 'spend',
            ],
        ]);

        $this->actingAs($staff);
        $submitted = app(SubmitSpendRequest::class)($staff, $request, null);

        $this->assertSame('in_review', (string) $submitted->status);
        $this->assertSame(2, (int) $submitted->current_approval_step);
        $this->assertDatabaseMissing('request_approvals', [
            'company_id' => $company->id,
            'request_id' => $submitted->id,
            'step_order' => 1,
        ]);
        $this->assertDatabaseHas('request_approvals', [
            'company_id' => $company->id,
            'request_id' => $submitted->id,
            'step_order' => 2,
            'status' => 'pending',
        ]);

        $fresh = $submitted->fresh();
        $this->assertFalse(Gate::forUser($manager)->allows('approve', $fresh));
        $this->assertTrue(Gate::forUser($finance)->allows('approve', $fresh));
    }

    public function test_approve_skips_out_of_range_future_steps_and_finalizes(): void
    {
        [$company, $department] = $this->createCompanyContext('Request Amount Range Decide');
        $manager = $this->createUser($company, $department, UserRole::Manager->value);
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value, [
            'reports_to_user_id' => $manager->id,
        ]);

        $workflow = $this->createDefaultRequestWorkflow($company, $manager);
        $this->createWorkflowStep($company, $workflow, 1, 'reports_to', null, ['in_app'], null, null);
        $this->createWorkflowStep($company, $workflow, 2, 'role', UserRole::Finance->value, ['in_app'], 500000, null);

        $request = $this->createRequest($company, $department, $staff, $workflow, [
            'request_code' => 'FD-REQ-RANGE-002',
            'status' => 'draft',
            'amount' => 100000,
            'metadata' => [
                'type' => 'spend',
                'request_type_code' => 'spend',
            ],
        ]);

        $this->actingAs($staff);
        $submitted = app(SubmitSpendRequest::class)($staff, $request, null);
        $this->assertSame(1, (int) $submitted->current_approval_step);

        $this->actingAs($manager);
        $decided = app(DecideSpendRequest::class)($manager, $submitted->fresh(), [
            'action' => 'approve',
            'comment' => '',
        ], null);

        $this->assertSame('approved', (string) $decided->status);
        $this->assertNull($decided->current_approval_step);
        $this->assertDatabaseMissing('request_approvals', [
            'company_id' => $company->id,
            'request_id' => $request->id,
            'step_order' => 2,
        ]);
        $this->assertFalse(Gate::forUser($finance)->allows('approve', $decided->fresh()));
    }

    public function test_upload_request_attachment_stores_file_and_row(): void
    {
        [$company, $department] = $this->createCompanyContext('Request Attachment Upload');
        $staff = $this->createUser($company, $department, UserRole::Staff->value);
        $request = $this->createRequest($company, $department, $staff, null, [
            'request_code' => 'FD-REQ-ATT-UP-001',
            'status' => 'draft',
            'metadata' => [
                'type' => 'spend',
                'request_type_code' => 'spend',
            ],
        ]);

        Storage::fake('local');
        $this->actingAs($staff);

        $file = UploadedFile::fake()->create('invoice.pdf', 120, 'application/pdf');
        $attachment = app(UploadRequestAttachment::class)($staff, $request, $file);

        $this->assertDatabaseHas('request_attachments', [
            'id' => $attachment->id,
            'company_id' => $company->id,
            'request_id' => $request->id,
            'original_name' => 'invoice.pdf',
            'uploaded_by' => $staff->id,
        ]);
        Storage::disk('local')->assertExists($attachment->file_path);
    }

    public function test_request_attachment_download_requires_access(): void
    {
        [$company, $department] = $this->createCompanyContext('Request Attachment Download');
        $requester = $this->createUser($company, $department, UserRole::Staff->value);
        $intruder = $this->createUser($company, $department, UserRole::Staff->value);
        $request = $this->createRequest($company, $department, $requester, null, [
            'request_code' => 'FD-REQ-ATT-DL-001',
            'status' => 'draft',
            'metadata' => [
                'type' => 'spend',
                'request_type_code' => 'spend',
            ],
        ]);

        Storage::fake('local');
        $this->actingAs($requester);
        $attachment = app(UploadRequestAttachment::class)(
            $requester,
            $request,
            UploadedFile::fake()->create('receipt.pdf', 64, 'application/pdf')
        );

        $this->get(route('requests.attachments.download', $attachment))
            ->assertOk()
            ->assertDownload('receipt.pdf');

        $this->actingAs($intruder);
        $this->get(route('requests.attachments.download', $attachment))
            ->assertForbidden();
    }

    public function test_submit_blocks_when_request_type_requires_attachment_but_none_uploaded(): void
    {
        [$company, $department] = $this->createCompanyContext('Request Attachment Required');
        $staff = $this->createUser($company, $department, UserRole::Staff->value);
        $this->createRequestType($company, 'Compliance', 'compliance', true);

        $request = $this->createRequest($company, $department, $staff, null, [
            'request_code' => 'FD-REQ-ATT-REQ-001',
            'status' => 'draft',
            'metadata' => [
                'type' => 'compliance',
                'request_type_code' => 'compliance',
                'request_type_name' => 'Compliance',
            ],
        ]);

        $this->actingAs($staff);

        try {
            app(SubmitSpendRequest::class)($staff, $request, null);
            $this->fail('Expected validation error for missing attachment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attachments', $exception->errors());
        }
    }

    public function test_submit_allows_when_required_attachment_is_uploaded(): void
    {
        [$company, $department] = $this->createCompanyContext('Request Attachment Required Pass');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value, [
            'reports_to_user_id' => $owner->id,
        ]);
        $this->createRequestType($company, 'Compliance', 'compliance', true);

        $workflow = $this->createDefaultRequestWorkflow($company, $owner);
        $this->createWorkflowStep($company, $workflow, 1, 'role', UserRole::Owner->value, ['in_app']);

        $request = $this->createRequest($company, $department, $staff, $workflow, [
            'request_code' => 'FD-REQ-ATT-REQ-002',
            'status' => 'draft',
            'workflow_id' => $workflow->id,
            'metadata' => [
                'type' => 'compliance',
                'request_type_code' => 'compliance',
                'request_type_name' => 'Compliance',
            ],
        ]);

        Storage::fake('local');
        $this->actingAs($staff);
        app(UploadRequestAttachment::class)(
            $staff,
            $request,
            UploadedFile::fake()->create('evidence.pdf', 84, 'application/pdf')
        );

        $submitted = app(SubmitSpendRequest::class)($staff, $request->fresh(), null);

        $this->assertSame('in_review', (string) $submitted->status);
        $this->assertSame(1, (int) $submitted->current_approval_step);
        $this->assertDatabaseHas('request_approvals', [
            'company_id' => $company->id,
            'request_id' => $request->id,
            'step_order' => 1,
            'status' => 'pending',
        ]);
    }

    public function test_submit_blocks_when_budget_policy_is_block_and_budget_is_exceeded(): void
    {
        [$company, $department] = $this->createCompanyContext('Request Budget Block');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value, [
            'reports_to_user_id' => $owner->id,
        ]);

        $this->createRequestPolicySetting($company, CompanyRequestPolicySetting::BUDGET_MODE_BLOCK, true, 30);
        $this->createActiveDepartmentBudget($company, $department, 100000);
        Expense::query()->create([
            'company_id' => $company->id,
            'expense_code' => 'FD-EXP-000111',
            'request_id' => null,
            'department_id' => $department->id,
            'vendor_id' => null,
            'title' => 'Existing spend',
            'description' => null,
            'amount' => 80000,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'paid_by_user_id' => $staff->id,
            'status' => 'posted',
            'is_direct' => true,
            'created_by' => $staff->id,
        ]);

        $workflow = $this->createDefaultRequestWorkflow($company, $owner);
        $this->createWorkflowStep($company, $workflow, 1, 'role', UserRole::Owner->value, ['in_app']);
        $request = $this->createRequest($company, $department, $staff, $workflow, [
            'request_code' => 'FD-REQ-BLOCK-001',
            'status' => 'draft',
            'amount' => 50000,
            'metadata' => [
                'type' => 'spend',
                'request_type_code' => 'spend',
            ],
        ]);

        $this->actingAs($staff);

        try {
            app(SubmitSpendRequest::class)($staff, $request, null);
            $this->fail('Expected budget block validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }
    }

    public function test_submit_warns_when_budget_policy_is_warn_and_budget_is_exceeded(): void
    {
        [$company, $department] = $this->createCompanyContext('Request Budget Warn');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value, [
            'reports_to_user_id' => $owner->id,
        ]);

        $this->createRequestPolicySetting($company, CompanyRequestPolicySetting::BUDGET_MODE_WARN, true, 30);
        $this->createActiveDepartmentBudget($company, $department, 100000);
        Expense::query()->create([
            'company_id' => $company->id,
            'expense_code' => 'FD-EXP-000112',
            'request_id' => null,
            'department_id' => $department->id,
            'vendor_id' => null,
            'title' => 'Existing spend',
            'description' => null,
            'amount' => 85000,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'paid_by_user_id' => $staff->id,
            'status' => 'posted',
            'is_direct' => true,
            'created_by' => $staff->id,
        ]);

        $workflow = $this->createDefaultRequestWorkflow($company, $owner);
        $this->createWorkflowStep($company, $workflow, 1, 'role', UserRole::Owner->value, ['in_app']);
        $request = $this->createRequest($company, $department, $staff, $workflow, [
            'request_code' => 'FD-REQ-WARN-001',
            'status' => 'draft',
            'amount' => 50000,
            'metadata' => [
                'type' => 'spend',
                'request_type_code' => 'spend',
            ],
        ]);

        $this->actingAs($staff);
        $submitted = app(SubmitSpendRequest::class)($staff, $request, null);

        $this->assertSame('in_review', (string) $submitted->status);
        $metadata = (array) ($submitted->metadata ?? []);
        $warnings = (array) ($metadata['policy_warnings'] ?? []);
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Budget warning', (string) $warnings[0]);
    }

    public function test_submit_warns_on_duplicate_when_duplicate_detection_enabled(): void
    {
        [$company, $department] = $this->createCompanyContext('Request Duplicate Warn');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value, [
            'reports_to_user_id' => $owner->id,
        ]);

        $this->createRequestPolicySetting($company, CompanyRequestPolicySetting::BUDGET_MODE_OFF, true, 30);

        $workflow = $this->createDefaultRequestWorkflow($company, $owner);
        $this->createWorkflowStep($company, $workflow, 1, 'role', UserRole::Owner->value, ['in_app']);

        $this->createRequest($company, $department, $staff, $workflow, [
            'request_code' => 'FD-REQ-DUP-001',
            'status' => 'in_review',
            'amount' => 120000,
            'title' => 'Laptop Purchase',
            'metadata' => [
                'type' => 'spend',
                'request_type_code' => 'spend',
            ],
        ]);

        $draft = $this->createRequest($company, $department, $staff, $workflow, [
            'request_code' => 'FD-REQ-DUP-002',
            'status' => 'draft',
            'amount' => 120000,
            'title' => 'Laptop Purchase',
            'metadata' => [
                'type' => 'spend',
                'request_type_code' => 'spend',
            ],
        ]);

        $this->actingAs($staff);
        $submitted = app(SubmitSpendRequest::class)($staff, $draft, null);

        $this->assertSame('in_review', (string) $submitted->status);
        $metadata = (array) ($submitted->metadata ?? []);
        $duplicate = (array) (($metadata['policy_checks'] ?? [])['duplicate'] ?? []);
        $this->assertSame('hard', (string) ($duplicate['risk'] ?? 'none'));
        $this->assertGreaterThanOrEqual(1, (int) ($duplicate['matches_count'] ?? 0));
        $this->assertNotEmpty((array) ($metadata['policy_warnings'] ?? []));
    }

    public function test_retry_service_resends_failed_email_log(): void
    {
        [$company, $department] = $this->createCompanyContext('Retry Failed Email');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $recipient = $this->createUser($company, $department, UserRole::Manager->value);
        $request = $this->createRequest($company, $department, $owner, null, [
            'request_code' => 'FD-REQ-RETRY-001',
            'status' => 'in_review',
        ]);

        CompanyCommunicationSetting::query()->create([
            'company_id' => $company->id,
            'in_app_enabled' => true,
            'email_enabled' => true,
            'sms_enabled' => false,
            'email_configured' => true,
            'sms_configured' => false,
            'fallback_order' => ['email', 'in_app', 'sms'],
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $log = RequestCommunicationLog::query()->create([
            'company_id' => $company->id,
            'request_id' => $request->id,
            'recipient_user_id' => $recipient->id,
            'event' => 'request.submitted',
            'channel' => 'email',
            'status' => 'failed',
            'message' => 'Initial send failed.',
            'metadata' => null,
        ]);

        $this->actingAs($owner);

        $retried = app(RequestCommunicationRetryService::class)->retryLog($log);

        $this->assertSame('sent', (string) $retried->status);
        $this->assertDatabaseHas('request_communication_logs', [
            'id' => $log->id,
            'status' => 'sent',
        ]);
    }

    public function test_retry_service_processes_stuck_queued_logs(): void
    {
        [$company, $department] = $this->createCompanyContext('Queued Processing');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $recipient = $this->createUser($company, $department, UserRole::Staff->value);
        $request = $this->createRequest($company, $department, $owner, null, [
            'request_code' => 'FD-REQ-QUEUE-001',
            'status' => 'in_review',
        ]);

        $log = RequestCommunicationLog::query()->create([
            'company_id' => $company->id,
            'request_id' => $request->id,
            'recipient_user_id' => $recipient->id,
            'event' => 'request.submitted',
            'channel' => 'in_app',
            'status' => 'queued',
            'message' => null,
            'metadata' => null,
        ]);
        $log->forceFill([
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ])->save();

        $this->actingAs($owner);

        $stats = app(RequestCommunicationRetryService::class)->processStuckQueued($company->id, 2, 50);

        $this->assertSame(1, $stats['processed']);
        $this->assertSame(1, $stats['sent']);
        $this->assertDatabaseHas('request_communication_logs', [
            'id' => $log->id,
            'status' => 'sent',
        ]);
    }

    public function test_staff_cannot_view_delivery_logs_or_execute_delivery_operations(): void
    {
        [$company, $department] = $this->createCompanyContext('Comm Role Guard Staff');
        $staff = $this->createUser($company, $department, UserRole::Staff->value);
        $request = $this->createRequest($company, $department, $staff, null, [
            'request_code' => 'FD-REQ-COMM-STAFF-001',
            'status' => 'in_review',
        ]);

        $failedLog = RequestCommunicationLog::query()->create([
            'company_id' => $company->id,
            'request_id' => $request->id,
            'recipient_user_id' => $staff->id,
            'event' => 'request.submitted',
            'channel' => 'in_app',
            'status' => 'failed',
            'message' => 'Failed at first attempt.',
            'metadata' => null,
        ]);

        $this->actingAs($staff);

        Livewire::test(RequestCommunicationsPage::class)
            ->call('switchTab', 'delivery')
            ->assertSet('activeTab', 'inbox')
            ->assertSet('feedbackError', 'You are not allowed to view delivery logs.')
            ->call('retryFailed')
            ->assertSet('feedbackError', 'You are not allowed to manage communication retry operations.');

        $this->assertDatabaseHas('request_communication_logs', [
            'id' => $failedLog->id,
            'status' => 'failed',
        ]);
    }

    public function test_manager_can_view_delivery_logs_but_cannot_execute_retry_operations(): void
    {
        [$company, $department] = $this->createCompanyContext('Comm Role Guard Manager');
        $manager = $this->createUser($company, $department, UserRole::Manager->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value, [
            'reports_to_user_id' => $manager->id,
        ]);
        $request = $this->createRequest($company, $department, $staff, null, [
            'request_code' => 'FD-REQ-COMM-MGR-001',
            'status' => 'in_review',
        ]);

        $failedLog = RequestCommunicationLog::query()->create([
            'company_id' => $company->id,
            'request_id' => $request->id,
            'recipient_user_id' => $staff->id,
            'event' => 'request.submitted',
            'channel' => 'in_app',
            'status' => 'failed',
            'message' => 'Failed at first attempt.',
            'metadata' => null,
        ]);

        $this->actingAs($manager);

        Livewire::test(RequestCommunicationsPage::class)
            ->call('switchTab', 'delivery')
            ->assertSet('activeTab', 'delivery')
            ->call('retryFailed')
            ->assertSet('feedbackError', 'You are not allowed to manage communication retry operations.');

        $this->assertDatabaseHas('request_communication_logs', [
            'id' => $failedLog->id,
            'status' => 'failed',
        ]);
    }

    public function test_owner_can_execute_retry_operations_from_communications_page(): void
    {
        [$company, $department] = $this->createCompanyContext('Comm Role Guard Owner');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value);
        $request = $this->createRequest($company, $department, $staff, null, [
            'request_code' => 'FD-REQ-COMM-OWNER-001',
            'status' => 'in_review',
        ]);

        $failedLog = RequestCommunicationLog::query()->create([
            'company_id' => $company->id,
            'request_id' => $request->id,
            'recipient_user_id' => $staff->id,
            'event' => 'request.submitted',
            'channel' => 'in_app',
            'status' => 'failed',
            'message' => 'Failed at first attempt.',
            'metadata' => null,
        ]);

        $this->actingAs($owner);

        Livewire::test(RequestCommunicationsPage::class)
            ->call('switchTab', 'delivery')
            ->assertSet('activeTab', 'delivery')
            ->call('retryFailed');

        $this->assertDatabaseHas('request_communication_logs', [
            'id' => $failedLog->id,
            'status' => 'sent',
        ]);
    }

    public function test_sla_processor_sends_reminder_for_pending_step(): void
    {
        [$company, $department] = $this->createCompanyContext('SLA Reminder');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $workflow = $this->createDefaultRequestWorkflow($company, $owner);
        $step = $this->createWorkflowStep($company, $workflow, 1, 'role', UserRole::Finance->value, ['in_app']);
        $request = $this->createRequest($company, $department, $staff, $workflow, [
            'request_code' => 'FD-REQ-SLA-REM-001',
            'status' => 'in_review',
            'current_approval_step' => 1,
        ]);
        $approval = $this->createPendingApproval($company, $request, $step, [
            'due_at' => now()->addMinutes(30),
            'reminder_sent_at' => null,
            'escalated_at' => null,
            'metadata' => [
                'actor_type' => 'role',
                'actor_value' => UserRole::Finance->value,
                'notification_channels' => ['in_app'],
                'sla' => [
                    'step_due_hours' => 24,
                    'reminder_hours_before_due' => 6,
                    'escalation_grace_hours' => 6,
                ],
            ],
        ]);

        $this->actingAs($owner);

        $stats = app(RequestApprovalSlaProcessor::class)->process($company->id, false);

        $this->assertGreaterThanOrEqual(1, $stats['reminders_sent']);
        $this->assertNotNull($approval->fresh()?->reminder_sent_at);
        $this->assertDatabaseHas('request_communication_logs', [
            'request_id' => $request->id,
            'request_approval_id' => $approval->id,
            'event' => 'request.approval.reminder',
            'channel' => 'in_app',
            'recipient_user_id' => $finance->id,
        ]);
    }

    public function test_sla_processor_escalates_overdue_step(): void
    {
        [$company, $department] = $this->createCompanyContext('SLA Escalation');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $manager = $this->createUser($company, $department, UserRole::Manager->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value, [
            'reports_to_user_id' => $manager->id,
        ]);

        $department->update(['manager_user_id' => $manager->id]);

        $workflow = $this->createDefaultRequestWorkflow($company, $owner);
        $step = $this->createWorkflowStep($company, $workflow, 1, 'role', UserRole::Finance->value, ['in_app']);
        $request = $this->createRequest($company, $department, $staff, $workflow, [
            'request_code' => 'FD-REQ-SLA-ESC-001',
            'status' => 'in_review',
            'current_approval_step' => 1,
        ]);
        $approval = $this->createPendingApproval($company, $request, $step, [
            'due_at' => now()->subHours(8),
            'reminder_sent_at' => now()->subHours(7),
            'escalated_at' => null,
            'metadata' => [
                'actor_type' => 'role',
                'actor_value' => UserRole::Finance->value,
                'notification_channels' => ['in_app'],
                'sla' => [
                    'step_due_hours' => 24,
                    'reminder_hours_before_due' => 6,
                    'escalation_grace_hours' => 6,
                ],
            ],
        ]);

        $this->actingAs($owner);

        $stats = app(RequestApprovalSlaProcessor::class)->process($company->id, false);

        $this->assertGreaterThanOrEqual(1, $stats['escalations_sent']);
        $this->assertNotNull($approval->fresh()?->escalated_at);
        $this->assertDatabaseHas('request_communication_logs', [
            'request_id' => $request->id,
            'request_approval_id' => $approval->id,
            'event' => 'request.approval.escalated',
            'channel' => 'in_app',
            'recipient_user_id' => $owner->id,
        ]);
    }

    public function test_request_automation_commands_run_successfully(): void
    {
        $this->artisan('requests:process-sla --dry-run')->assertExitCode(0);
        $this->artisan('requests:communications:retry-failed --batch=20')->assertExitCode(0);
        $this->artisan('requests:communications:process-queued --older-than=2 --batch=20')->assertExitCode(0);
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

    private function createDefaultRequestWorkflow(Company $company, User $creator): ApprovalWorkflow
    {
        return ApprovalWorkflow::query()->create([
            'company_id' => $company->id,
            'name' => 'Default Request Workflow',
            'code' => 'default_request_'.Str::lower(Str::random(4)),
            'applies_to' => 'request',
            'is_active' => true,
            'is_default' => true,
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);
    }

    /**
     * @param  array<int, string>  $channels
     */
    private function createWorkflowStep(
        Company $company,
        ApprovalWorkflow $workflow,
        int $stepOrder,
        string $actorType,
        ?string $actorValue = null,
        array $channels = ['in_app'],
        ?int $minAmount = null,
        ?int $maxAmount = null
    ): ApprovalWorkflowStep {
        return ApprovalWorkflowStep::query()->create([
            'company_id' => $company->id,
            'workflow_id' => $workflow->id,
            'step_order' => $stepOrder,
            'step_key' => 'step_'.$stepOrder,
            'actor_type' => $actorType,
            'actor_value' => $actorValue,
            'min_amount' => $minAmount,
            'max_amount' => $maxAmount,
            'notification_channels' => $channels,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createRequest(
        Company $company,
        Department $department,
        User $requester,
        ?ApprovalWorkflow $workflow,
        array $overrides = []
    ): SpendRequest {
        return SpendRequest::query()->create(array_merge([
            'company_id' => $company->id,
            'request_code' => 'FD-REQ-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'requested_by' => $requester->id,
            'department_id' => $department->id,
            'vendor_id' => null,
            'workflow_id' => $workflow?->id,
            'title' => 'Automation request',
            'description' => 'Automated test request',
            'amount' => 175000,
            'currency' => 'NGN',
            'status' => 'draft',
            'approved_amount' => null,
            'paid_amount' => 0,
            'current_approval_step' => null,
            'submitted_at' => now(),
            'decided_at' => null,
            'decision_note' => null,
            'metadata' => null,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPendingApproval(
        Company $company,
        SpendRequest $request,
        ApprovalWorkflowStep $step,
        array $overrides = []
    ): RequestApproval {
        return RequestApproval::query()->create(array_merge([
            'company_id' => $company->id,
            'request_id' => $request->id,
            'workflow_step_id' => $step->id,
            'step_order' => (int) $step->step_order,
            'step_key' => $step->step_key,
            'status' => 'pending',
            'action' => null,
            'acted_by' => null,
            'acted_at' => null,
            'due_at' => null,
            'reminder_sent_at' => null,
            'escalated_at' => null,
            'reminder_count' => 0,
            'comment' => null,
            'from_status' => null,
            'to_status' => null,
            'metadata' => [
                'actor_type' => $step->actor_type,
                'actor_value' => $step->actor_value,
                'notification_channels' => (array) ($step->notification_channels ?? ['in_app']),
                'sla' => [
                    'step_due_hours' => 24,
                    'reminder_hours_before_due' => 6,
                    'escalation_grace_hours' => 6,
                ],
            ],
        ], $overrides));
    }

    private function createRequestType(Company $company, string $name, string $code, bool $requiresAttachments): CompanyRequestType
    {
        return CompanyRequestType::query()->create([
            'company_id' => $company->id,
            'name' => $name,
            'code' => strtolower($code),
            'description' => null,
            'is_active' => true,
            'requires_amount' => true,
            'requires_line_items' => false,
            'requires_date_range' => false,
            'requires_vendor' => false,
            'requires_attachments' => $requiresAttachments,
            'metadata' => null,
            'created_by' => null,
            'updated_by' => null,
        ]);
    }

    private function createRequestPolicySetting(
        Company $company,
        string $budgetMode,
        bool $duplicateEnabled,
        int $windowDays
    ): CompanyRequestPolicySetting {
        return CompanyRequestPolicySetting::query()->create([
            'company_id' => $company->id,
            'budget_guardrail_mode' => $budgetMode,
            'duplicate_detection_enabled' => $duplicateEnabled,
            'duplicate_window_days' => $windowDays,
            'metadata' => null,
            'created_by' => null,
            'updated_by' => null,
        ]);
    }

    private function createActiveDepartmentBudget(Company $company, Department $department, int $allocatedAmount): DepartmentBudget
    {
        return DepartmentBudget::query()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'period_type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'allocated_amount' => $allocatedAmount,
            'used_amount' => 0,
            'remaining_amount' => $allocatedAmount,
            'status' => 'active',
            'created_by' => null,
        ]);
    }
}
