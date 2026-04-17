<?php

namespace Tests\Feature\Requests;

use App\Domains\Assets\Models\Asset;
use App\Domains\Assets\Models\AssetCommunicationLog;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\TenantFeatureEntitlement;
use App\Domains\Requests\Models\RequestCommunicationLog;
use App\Domains\Requests\Models\SpendRequest;
use App\Domains\Vendors\Models\Vendor;
use App\Domains\Vendors\Models\VendorCommunicationLog;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Enums\UserRole;
use App\Livewire\Requests\RequestCommunicationsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CommunicationsRecoveryDeskPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_communications_desk_and_help_page(): void
    {
        [$company, $department] = $this->createCompanyContext('Communications Recovery Owner');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'requests_enabled' => true,
            'communications_enabled' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('requests.communications'))
            ->assertOk()
            ->assertSee('Notification Recovery');

        $this->actingAs($owner)
            ->get(route('requests.communications-help'))
            ->assertOk()
            ->assertSee('Notification Recovery');
    }

    public function test_owner_can_retry_failed_rows_across_requests_vendors_and_assets(): void
    {
        [$company, $department] = $this->createCompanyContext('Communications Recovery Retry');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $recipient = $this->createUser($company, $department, UserRole::Staff->value, [
            'reports_to_user_id' => $owner->id,
        ]);

        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'requests_enabled' => true,
            'communications_enabled' => true,
            'vendors_enabled' => true,
            'assets_enabled' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $vendor = Vendor::query()->create([
            'company_id' => $company->id,
            'name' => 'Recovery Vendor',
            'email' => 'vendor.recovery@example.test',
            'is_active' => true,
        ]);

        $invoice = VendorInvoice::query()->create([
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'invoice_number' => 'INV-REC-001',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'currency' => 'NGN',
            'total_amount' => 120000,
            'paid_amount' => 0,
            'outstanding_amount' => 120000,
            'status' => 'unpaid',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $request = SpendRequest::query()->create([
            'company_id' => $company->id,
            'request_code' => 'FD-REQ-COMM-REC-001',
            'requested_by' => $recipient->id,
            'department_id' => $department->id,
            'vendor_id' => $vendor->id,
            'title' => 'Recovery request row',
            'description' => 'Recovery test',
            'amount' => 55000,
            'currency' => 'NGN',
            'status' => 'in_review',
            'created_by' => $recipient->id,
            'updated_by' => $recipient->id,
        ]);

        $asset = Asset::query()->create([
            'company_id' => $company->id,
            'asset_code' => 'AST-REC-001',
            'name' => 'Recovery Asset',
            'status' => 'active',
            'condition' => 'good',
            'currency' => 'NGN',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $requestLog = RequestCommunicationLog::query()->create([
            'company_id' => $company->id,
            'request_id' => $request->id,
            'recipient_user_id' => $recipient->id,
            'event' => 'request.submitted',
            'channel' => 'in_app',
            'status' => 'failed',
            'message' => 'Failed at first attempt.',
        ]);

        $vendorLog = VendorCommunicationLog::query()->create([
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'vendor_invoice_id' => $invoice->id,
            'recipient_user_id' => $recipient->id,
            'event' => 'vendor.internal.overdue.reminder',
            'channel' => 'in_app',
            'status' => 'failed',
            'reminder_date' => now()->toDateString(),
            'message' => 'Failed at first attempt.',
        ]);

        $assetLog = AssetCommunicationLog::query()->create([
            'company_id' => $company->id,
            'asset_id' => $asset->id,
            'recipient_user_id' => $recipient->id,
            'event' => 'asset.internal.maintenance.overdue',
            'channel' => 'in_app',
            'status' => 'failed',
            'reminder_date' => now()->toDateString(),
            'message' => 'Failed at first attempt.',
        ]);

        $this->actingAs($owner);

        Livewire::test(RequestCommunicationsPage::class)
            ->call('switchTab', 'delivery')
            ->set('displayScope', 'all')
            ->call('retryFailed')
            ->assertSet('feedbackError', null);

        $this->assertDatabaseHas('request_communication_logs', [
            'id' => $requestLog->id,
            'status' => 'sent',
        ]);

        $this->assertDatabaseHas('vendor_communication_logs', [
            'id' => $vendorLog->id,
            'status' => 'sent',
        ]);

        $this->assertDatabaseHas('asset_communication_logs', [
            'id' => $assetLog->id,
            'status' => 'sent',
        ]);
    }

    public function test_staff_cannot_open_communications_recovery_help_page(): void
    {
        [$company, $department] = $this->createCompanyContext('Communications Recovery Staff');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'requests_enabled' => true,
            'communications_enabled' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($staff)
            ->get(route('requests.communications-help'))
            ->assertForbidden();
    }

    public function test_staff_cannot_force_delivery_tab_via_state_tampering(): void
    {
        [$company, $department] = $this->createCompanyContext('Communications Recovery Staff Tamper');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        TenantFeatureEntitlement::query()->create([
            'company_id' => $company->id,
            'requests_enabled' => true,
            'communications_enabled' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $request = SpendRequest::query()->create([
            'company_id' => $company->id,
            'request_code' => 'REQ-COMM-TAMPER-001',
            'requested_by' => $staff->id,
            'department_id' => $department->id,
            'title' => 'Tamper visibility request',
            'amount' => 50000,
            'currency' => 'NGN',
            'status' => 'in_review',
            'created_by' => $staff->id,
            'updated_by' => $staff->id,
        ]);

        RequestCommunicationLog::query()->create([
            'company_id' => $company->id,
            'request_id' => $request->id,
            'recipient_user_id' => $owner->id,
            'event' => 'request.delivery.test',
            'channel' => 'email',
            'status' => 'failed',
            'message' => 'Delivery-only failure row',
        ]);

        $this->actingAs($staff);

        Livewire::test(RequestCommunicationsPage::class)
            ->call('loadData')
            ->set('activeTab', 'delivery-hijack')
            ->call('$refresh')
            ->assertSet('activeTab', 'inbox')
            ->assertDontSee('Delivery-only failure row');
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+comm-recovery@example.test',
            'is_active' => true,
            'lifecycle_status' => 'active',
            'currency_code' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);

        $department = Department::query()->create([
            'company_id' => $company->id,
            'name' => 'Operations',
            'code' => 'OPS',
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
        ], $overrides));
    }
}

