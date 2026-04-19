<?php

namespace Tests\Feature\Accounting;

use App\Actions\Accounting\ExportAccountingCsv;
use App\Actions\Expenses\CreateExpense;
use App\Domains\Accounting\Models\AccountingExportBatch;
use App\Domains\Accounting\Models\AccountingSyncEvent;
use App\Domains\Accounting\Models\ChartOfAccountMapping;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Enums\AccountingCategory;
use App\Enums\AccountingSyncStatus;
use App\Enums\UserRole;
use App\Livewire\Reports\AccountingExportPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingExportPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_can_export_ready_accounting_records_to_csv(): void
    {
        Storage::fake('local');

        [$company, $department] = $this->createCompanyContext('Accounting Export Finance');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $this->mapCategory($company, $finance, AccountingCategory::SpendOperations->value, '5000');

        $this->actingAs($finance);

        $expense = app(CreateExpense::class)($finance, [
            ...$this->expensePayload($department),
            'accounting_category_key' => AccountingCategory::SpendOperations->value,
        ]);
        $event = AccountingSyncEvent::query()
            ->where('company_id', $company->id)
            ->where('source_type', 'expense')
            ->where('source_id', $expense->id)
            ->firstOrFail();
        $this->assertSame(AccountingSyncStatus::Pending->value, (string) $event->status);
        $this->assertSame('csv', (string) $event->provider);
        $eventDate = substr((string) $event->getRawOriginal('event_date'), 0, 10);

        $batch = app(ExportAccountingCsv::class)($finance, [
            'from_date' => $eventDate,
            'to_date' => $eventDate,
        ]);

        Storage::disk('local')->assertExists((string) $batch->file_path);

        $this->assertSame(1, (int) $batch->row_count);
        $this->assertDatabaseHas('accounting_sync_events', [
            'company_id' => $company->id,
            'source_type' => 'expense',
            'source_id' => $expense->id,
            'status' => AccountingSyncStatus::Exported->value,
            'export_batch_id' => $batch->id,
        ]);

        $csv = Storage::disk('local')->get((string) $batch->file_path);
        $this->assertStringContainsString('Date,Reference,"Source Type",Description,"Debit Account"', $csv);
        $this->assertStringContainsString('5000', $csv);
    }

    public function test_export_is_blocked_when_records_need_mapping(): void
    {
        Storage::fake('local');

        [$company, $department] = $this->createCompanyContext('Accounting Export Missing');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);

        $this->actingAs($finance);

        $expense = app(CreateExpense::class)($finance, [
            ...$this->expensePayload($department),
            'accounting_category_key' => AccountingCategory::SpendSoftware->value,
        ]);
        $event = AccountingSyncEvent::query()
            ->where('company_id', $company->id)
            ->where('source_type', 'expense')
            ->where('source_id', $expense->id)
            ->firstOrFail();
        $this->assertSame(AccountingSyncStatus::NeedsMapping->value, (string) $event->status);
        $this->assertSame('csv', (string) $event->provider);
        $eventDate = substr((string) $event->getRawOriginal('event_date'), 0, 10);

        try {
            app(ExportAccountingCsv::class)($finance, [
                'from_date' => $eventDate,
                'to_date' => $eventDate,
            ]);
            $this->fail('Expected missing account mapping to block CSV export.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                '1 accounting record(s) need Chart of Accounts mapping before export.',
                (string) collect($exception->errors())->flatten()->first()
            );
        }

        $this->assertSame(0, AccountingExportBatch::query()->where('company_id', $company->id)->count());
        $this->assertSame(AccountingSyncStatus::NeedsMapping->value, (string) AccountingSyncEvent::query()->where('company_id', $company->id)->firstOrFail()->status);
    }

    public function test_auditor_can_view_export_page_but_cannot_export(): void
    {
        [$company, $department] = $this->createCompanyContext('Accounting Export Auditor');
        $auditor = $this->createUser($company, $department, UserRole::Auditor->value);

        $this->actingAs($auditor)
            ->get(route('reports.accounting-export'))
            ->assertOk()
            ->assertSee('View-only access')
            ->assertDontSee('Export CSV');

        Livewire::actingAs($auditor)
            ->test(AccountingExportPage::class)
            ->assertSet('canExport', false);
    }

    public function test_staff_cannot_access_accounting_export_page(): void
    {
        [$company, $department] = $this->createCompanyContext('Accounting Export Staff');
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $this->actingAs($staff)
            ->get(route('reports.accounting-export'))
            ->assertForbidden();
    }

    public function test_export_download_is_scoped_to_same_company(): void
    {
        Storage::fake('local');

        [$companyA, $departmentA] = $this->createCompanyContext('Accounting Export A');
        [$companyB, $departmentB] = $this->createCompanyContext('Accounting Export B');
        $financeA = $this->createUser($companyA, $departmentA, UserRole::Finance->value);
        $financeB = $this->createUser($companyB, $departmentB, UserRole::Finance->value);

        $batchB = AccountingExportBatch::query()->withoutGlobalScopes()->create([
            'company_id' => (int) $companyB->id,
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->toDateString(),
            'status' => 'completed',
            'row_count' => 1,
            'warning_count' => 0,
            'file_path' => 'private/accounting-exports/'.$companyB->id.'/test.csv',
            'created_by' => (int) $financeB->id,
            'metadata' => ['provider' => 'csv'],
        ]);
        Storage::disk('local')->put((string) $batchB->file_path, "Date,Reference\n");

        $this->actingAs($financeA)
            ->get(route('reports.accounting-export.download', $batchB))
            ->assertNotFound();
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+export@example.test',
            'is_active' => true,
            'lifecycle_status' => 'active',
            'currency_code' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);

        $department = Department::query()->create([
            'company_id' => $company->id,
            'name' => 'Finance',
            'code' => 'FIN',
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

    private function mapCategory(Company $company, User $actor, string $categoryKey, string $accountCode): void
    {
        ChartOfAccountMapping::query()->create([
            'company_id' => (int) $company->id,
            'provider' => 'csv',
            'category_key' => $categoryKey,
            'account_code' => $accountCode,
            'account_name' => AccountingCategory::labelFor($categoryKey),
            'created_by' => (int) $actor->id,
            'updated_by' => (int) $actor->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function expensePayload(Department $department): array
    {
        return [
            'department_id' => (int) $department->id,
            'vendor_id' => null,
            'title' => 'CSV export expense',
            'description' => 'Expense used to verify accounting CSV export.',
            'amount' => 65000,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'paid_by_user_id' => null,
        ];
    }
}
