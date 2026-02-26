<?php

namespace Tests\Feature\Assets;

use App\Actions\Assets\AssignAsset;
use App\Actions\Assets\CreateAsset;
use App\Actions\Assets\CreateAssetCategory;
use App\Actions\Assets\DisposeAsset;
use App\Actions\Assets\RecordAssetMaintenance;
use App\Actions\Assets\ReturnAsset;
use App\Domains\Assets\Models\Asset;
use App\Domains\Assets\Models\CompanyAssetPolicySetting;
use App\Domains\Assets\Models\AssetCategory;
use App\Domains\Assets\Models\AssetCommunicationLog;
use App\Domains\Assets\Models\AssetEvent;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;
use App\Livewire\Assets\AssetsPage;

class AssetModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_register_asset_with_category_and_created_event(): void
    {
        [$company, $department] = $this->createCompanyContext('Acme Assets');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $category = app(CreateAssetCategory::class)($owner, [
            'name' => 'Computers',
            'description' => 'Laptops and desktops',
            'is_active' => true,
        ]);
        $this->assertSame('COMPUTERS', (string) $category->code);

        $this->actingAs($owner);
        $asset = app(CreateAsset::class)($owner, [
            'asset_category_id' => $category->id,
            'name' => 'Dell Latitude',
            'serial_number' => 'SN-001',
            'acquisition_date' => now()->toDateString(),
            'purchase_amount' => 850000,
            'currency' => 'NGN',
            'condition' => 'excellent',
            'notes' => 'Primary operations laptop',
        ]);

        $this->assertSame((int) $company->id, (int) $asset->company_id);
        $this->assertStringStartsWith('FD-AST-', (string) $asset->asset_code);
        $this->assertDatabaseHas('asset_events', [
            'company_id' => $company->id,
            'asset_id' => $asset->id,
            'event_type' => AssetEvent::TYPE_CREATED,
        ]);
    }

    public function test_manager_can_assign_and_transfer_asset_with_history_events(): void
    {
        [$company, $department] = $this->createCompanyContext('Acme Custody');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $manager = $this->createUser($company, $department, UserRole::Manager->value);
        $staffOne = $this->createUser($company, $department, UserRole::Staff->value);
        $staffTwo = $this->createUser($company, $department, UserRole::Staff->value);

        $asset = app(CreateAsset::class)($owner, [
            'name' => 'Inventory Scanner',
            'serial_number' => 'INV-SCAN-100',
            'condition' => 'good',
        ]);

        $this->actingAs($manager);
        app(AssignAsset::class)($manager, $asset, [
            'target_user_id' => $staffOne->id,
            'target_department_id' => $department->id,
            'event_date' => now()->toDateString(),
            'summary' => 'Issued to logistics',
            'details' => 'Initial deployment.',
        ]);

        app(AssignAsset::class)($manager, $asset->refresh(), [
            'target_user_id' => $staffTwo->id,
            'target_department_id' => $department->id,
            'event_date' => now()->addDay()->toDateString(),
            'summary' => 'Reassigned to floor ops',
            'details' => 'Shift custody to second operator.',
        ]);

        $asset->refresh();
        $this->assertSame((int) $staffTwo->id, (int) $asset->assigned_to_user_id);
        $this->assertSame(1, AssetEvent::query()->where('asset_id', $asset->id)->where('event_type', AssetEvent::TYPE_ASSIGNED)->count());
        $this->assertSame(1, AssetEvent::query()->where('asset_id', $asset->id)->where('event_type', AssetEvent::TYPE_TRANSFERRED)->count());
    }

    public function test_finance_can_log_maintenance(): void
    {
        [$company, $department] = $this->createCompanyContext('Acme Maintenance');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $finance = $this->createUser($company, $department, UserRole::Finance->value);

        $asset = app(CreateAsset::class)($owner, [
            'name' => 'Office Printer',
            'serial_number' => 'PRT-220',
            'condition' => 'fair',
        ]);

        $this->actingAs($finance);
        $event = app(RecordAssetMaintenance::class)($finance, $asset, [
            'event_date' => now()->toDateString(),
            'summary' => 'Toner replacement',
            'amount' => 25000,
            'currency' => 'NGN',
            'details' => 'Replaced toner and cleaned rollers.',
        ]);

        $this->assertSame(AssetEvent::TYPE_MAINTENANCE, $event->event_type);
        $this->assertDatabaseHas('asset_events', [
            'id' => $event->id,
            'asset_id' => $asset->id,
            'event_type' => AssetEvent::TYPE_MAINTENANCE,
        ]);
        $this->assertNotNull($asset->refresh()->last_maintenance_at);
    }

    public function test_disposed_asset_cannot_be_assigned_again(): void
    {
        [$company, $department] = $this->createCompanyContext('Acme Disposal');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $manager = $this->createUser($company, $department, UserRole::Manager->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $asset = app(CreateAsset::class)($owner, [
            'name' => 'Legacy Router',
            'serial_number' => 'RTR-OLD',
            'condition' => 'poor',
        ]);

        $this->actingAs($owner);
        app(DisposeAsset::class)($owner, $asset, [
            'event_date' => now()->toDateString(),
            'summary' => 'End-of-life disposal',
            'salvage_amount' => 5000,
            'details' => 'Replaced with new hardware.',
        ]);

        $this->assertSame(Asset::STATUS_DISPOSED, (string) $asset->refresh()->status);

        $this->actingAs($manager);
        $this->expectException(ValidationException::class);
        app(AssignAsset::class)($manager, $asset, [
            'target_user_id' => $staff->id,
            'target_department_id' => $department->id,
            'event_date' => now()->addDay()->toDateString(),
            'summary' => 'Attempted reassignment',
        ]);
    }

    public function test_manager_can_return_assigned_asset_to_inventory(): void
    {
        [$company, $department] = $this->createCompanyContext('Acme Returns');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $manager = $this->createUser($company, $department, UserRole::Manager->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $asset = app(CreateAsset::class)($owner, [
            'name' => 'Tablet Device',
            'serial_number' => 'TAB-001',
            'condition' => 'good',
        ]);

        app(AssignAsset::class)($manager, $asset, [
            'target_user_id' => $staff->id,
            'target_department_id' => $department->id,
            'event_date' => now()->toDateString(),
            'summary' => 'Assigned for field ops',
        ]);

        app(ReturnAsset::class)($manager, $asset->refresh(), [
            'event_date' => now()->addDay()->toDateString(),
            'summary' => 'Returned after field use',
        ]);

        $asset->refresh();
        $this->assertNull($asset->assigned_to_user_id);
        $this->assertNull($asset->assigned_department_id);
        $this->assertSame(Asset::STATUS_ACTIVE, (string) $asset->status);
        $this->assertSame(1, AssetEvent::query()->where('asset_id', $asset->id)->where('event_type', AssetEvent::TYPE_RETURNED)->count());
    }

    public function test_staff_cannot_register_asset(): void
    {
        [$company, $department] = $this->createCompanyContext('Acme Staff Restriction');
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $this->actingAs($staff);
        $this->expectException(AuthorizationException::class);

        app(CreateAsset::class)($staff, [
            'name' => 'Unauthorized Device',
            'condition' => 'good',
        ]);
    }

    public function test_asset_category_code_is_auto_generated_and_manual_code_is_rejected(): void
    {
        [$company, $department] = $this->createCompanyContext('Acme Category Codes');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $first = app(CreateAssetCategory::class)($owner, [
            'name' => 'Office Equipment',
            'is_active' => true,
        ]);
        $this->assertSame('OFFICE_EQUIPMENT', (string) $first->code);

        $this->expectException(ValidationException::class);
        app(CreateAssetCategory::class)($owner, [
            'name' => 'Server Rack',
            'code' => 'MANUAL_CODE',
            'is_active' => true,
        ]);
    }

    public function test_asset_controls_can_restrict_assignment_by_role(): void
    {
        [$company, $department] = $this->createCompanyContext('Acme Asset Controls');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $manager = $this->createUser($company, $department, UserRole::Manager->value);
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        CompanyAssetPolicySetting::query()->create([
            'company_id' => $company->id,
            'action_policies' => [
                CompanyAssetPolicySetting::ACTION_VIEW_REGISTRY => ['allowed_roles' => UserRole::values()],
                CompanyAssetPolicySetting::ACTION_REGISTER_ASSET => ['allowed_roles' => [UserRole::Owner->value, UserRole::Finance->value, UserRole::Manager->value]],
                CompanyAssetPolicySetting::ACTION_EDIT_ASSET => ['allowed_roles' => [UserRole::Owner->value, UserRole::Finance->value, UserRole::Manager->value]],
                CompanyAssetPolicySetting::ACTION_ASSIGN_TRANSFER_RETURN => ['allowed_roles' => [UserRole::Finance->value]],
                CompanyAssetPolicySetting::ACTION_LOG_MAINTENANCE => ['allowed_roles' => [UserRole::Owner->value, UserRole::Finance->value, UserRole::Manager->value]],
                CompanyAssetPolicySetting::ACTION_DISPOSE_ASSET => ['allowed_roles' => [UserRole::Owner->value, UserRole::Finance->value]],
            ],
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $asset = app(CreateAsset::class)($owner, [
            'name' => 'Policy Locked Device',
            'serial_number' => 'POL-001',
            'condition' => 'good',
        ]);

        $this->expectException(AuthorizationException::class);
        app(AssignAsset::class)($manager, $asset, [
            'target_user_id' => $staff->id,
            'target_department_id' => $department->id,
            'event_date' => now()->toDateString(),
            'summary' => 'Manager assignment attempt',
        ]);
    }

    public function test_manager_can_bulk_assign_assets_from_assets_page(): void
    {
        [$company, $department] = $this->createCompanyContext('Acme Bulk Assign');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $manager = $this->createUser($company, $department, UserRole::Manager->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $assetA = app(CreateAsset::class)($owner, [
            'name' => 'Bulk Laptop A',
            'serial_number' => 'BULK-A',
            'condition' => 'good',
        ]);
        $assetB = app(CreateAsset::class)($owner, [
            'name' => 'Bulk Laptop B',
            'serial_number' => 'BULK-B',
            'condition' => 'good',
        ]);

        $this->actingAs($manager);

        Livewire::test(AssetsPage::class)
            ->set('readyToLoad', true)
            ->set('selectedAssetIds', [$assetA->id, $assetB->id])
            ->call('openBulkActionModal', 'assign')
            ->set('bulkForm.target_user_id', (string) $staff->id)
            ->set('bulkForm.target_department_id', (string) $department->id)
            ->set('bulkForm.event_date', now()->toDateString())
            ->set('bulkForm.summary', 'Bulk assignment test')
            ->call('saveBulkAction')
            ->assertSet('showBulkActionModal', false)
            ->assertSet('selectedAssetIds', []);

        $this->assertSame((int) $staff->id, (int) $assetA->fresh()->assigned_to_user_id);
        $this->assertSame((int) $staff->id, (int) $assetB->fresh()->assigned_to_user_id);
        $this->assertSame(2, AssetEvent::query()->whereIn('asset_id', [$assetA->id, $assetB->id])->where('event_type', AssetEvent::TYPE_ASSIGNED)->count());
    }

    public function test_assignment_queues_in_app_notification_for_assignee(): void
    {
        [$company, $department] = $this->createCompanyContext('Acme Asset Assignment Inbox');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $manager = $this->createUser($company, $department, UserRole::Manager->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $asset = app(CreateAsset::class)($owner, [
            'name' => 'Assignment Inbox Device',
            'serial_number' => 'ASSIGN-INBOX-001',
            'condition' => 'good',
        ]);

        app(AssignAsset::class)($manager, $asset, [
            'target_user_id' => $staff->id,
            'target_department_id' => $department->id,
            'event_date' => now()->format('Y-m-d H:i:s'),
            'summary' => 'Assigned for staff operations',
        ]);

        $this->assertDatabaseHas('asset_communication_logs', [
            'company_id' => $company->id,
            'asset_id' => $asset->id,
            'recipient_user_id' => $staff->id,
            'event' => 'asset.internal.assignment.assigned',
            'channel' => 'in_app',
        ]);

        $this->assertGreaterThanOrEqual(
            1,
            AssetCommunicationLog::query()
                ->where('asset_id', $asset->id)
                ->where('recipient_user_id', $staff->id)
                ->count()
        );
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $companyName): array
    {
        $company = Company::query()->create([
            'name' => $companyName,
            'slug' => Str::slug($companyName).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($companyName).'+company@example.test',
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
