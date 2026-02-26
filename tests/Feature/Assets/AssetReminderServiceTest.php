<?php

namespace Tests\Feature\Assets;

use App\Actions\Assets\CreateAsset;
use App\Domains\Assets\Models\AssetCommunicationLog;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\AssetReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssetReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_asset_reminders_for_due_dates(): void
    {
        [$company, $department] = $this->createCompanyContext('Acme Asset Reminders');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $asset = app(CreateAsset::class)($owner, [
            'name' => 'Reminder Target Asset',
            'serial_number' => 'REM-001',
            'condition' => 'good',
            'assigned_to_user_id' => $staff->id,
            'assigned_department_id' => $department->id,
            'maintenance_due_date' => now()->toDateString(),
            'warranty_expires_at' => now()->addDay()->toDateString(),
        ]);

        $stats = app(AssetReminderService::class)->dispatchDueReminders((int) $company->id, 7);

        $this->assertSame(1, (int) $stats['scanned']);
        $this->assertGreaterThanOrEqual(1, (int) $stats['queued']);
        $this->assertDatabaseHas('asset_communication_logs', [
            'company_id' => $company->id,
            'asset_id' => $asset->id,
            'channel' => 'in_app',
        ]);

        // Ensure reminders were addressed to at least finance or current assignee.
        $recipientIds = AssetCommunicationLog::query()
            ->where('asset_id', (int) $asset->id)
            ->pluck('recipient_user_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $this->assertContains((int) $finance->id, $recipientIds);
        $this->assertContains((int) $staff->id, $recipientIds);
    }

    public function test_it_deduplicates_asset_reminders_for_same_day(): void
    {
        [$company, $department] = $this->createCompanyContext('Acme Asset Reminder Dedupe');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $this->createUser($company, $department, UserRole::Finance->value);

        app(CreateAsset::class)($owner, [
            'name' => 'Deduped Asset',
            'serial_number' => 'DED-001',
            'condition' => 'good',
            'maintenance_due_date' => now()->toDateString(),
        ]);

        $service = app(AssetReminderService::class);
        $first = $service->dispatchDueReminders((int) $company->id, 7);
        $initialCount = AssetCommunicationLog::query()->count();

        $second = $service->dispatchDueReminders((int) $company->id, 7);
        $afterCount = AssetCommunicationLog::query()->count();

        $this->assertGreaterThanOrEqual(1, (int) $first['queued']);
        $this->assertGreaterThanOrEqual(1, (int) $second['duplicates']);
        $this->assertSame($initialCount, $afterCount);
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

