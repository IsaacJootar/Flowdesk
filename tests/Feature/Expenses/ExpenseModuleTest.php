<?php

namespace Tests\Feature\Expenses;

use App\Actions\Expenses\CreateExpense;
use App\Actions\Expenses\UpdateExpense;
use App\Actions\Expenses\UploadExpenseAttachment;
use App\Actions\Expenses\VoidExpense;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Vendors\Models\Vendor;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExpenseModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_can_create_direct_expense_and_log_activity(): void
    {
        [$company, $department] = $this->createCompanyContext('Expense HQ');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $vendor = $this->createVendor($company);

        $this->actingAs($finance);

        $expense = app(CreateExpense::class)($finance, $this->validExpensePayload($department, $vendor));

        $this->assertSame($company->id, (int) $expense->company_id);
        $this->assertSame('posted', $expense->status);
        $this->assertTrue((bool) $expense->is_direct);
        $this->assertStringStartsWith('FD-EXP-', $expense->expense_code);
        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $company->id,
            'user_id' => $finance->id,
            'action' => 'expense.created',
            'entity_type' => Expense::class,
            'entity_id' => $expense->id,
        ]);
    }

    public function test_manager_cannot_create_expense(): void
    {
        [$company, $department] = $this->createCompanyContext('Expense Manager');
        $manager = $this->createUser($company, $department, UserRole::Manager->value);

        $this->actingAs($manager);
        $this->expectException(AuthorizationException::class);

        app(CreateExpense::class)($manager, $this->validExpensePayload($department, null));
    }

    public function test_finance_can_update_expense_and_log_activity(): void
    {
        [$company, $department] = $this->createCompanyContext('Expense Update');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $vendor = $this->createVendor($company);
        $expense = $this->createExpense($company, $department, $finance, $vendor);

        $this->actingAs($finance);

        app(UpdateExpense::class)($finance, $expense, [
            ...$this->validExpensePayload($department, $vendor),
            'title' => 'Updated expense title',
            'amount' => 98000,
            'payment_method' => 'online',
        ]);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'title' => 'Updated expense title',
            'amount' => 98000,
            'payment_method' => 'online',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $company->id,
            'user_id' => $finance->id,
            'action' => 'expense.updated',
            'entity_type' => Expense::class,
            'entity_id' => $expense->id,
        ]);
    }

    public function test_finance_can_void_expense_with_reason_and_log_activity(): void
    {
        [$company, $department] = $this->createCompanyContext('Expense Void');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $expense = $this->createExpense($company, $department, $finance, null);

        $this->actingAs($finance);

        app(VoidExpense::class)($finance, $expense, ['reason' => 'Duplicate posting in cashbook']);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'status' => 'void',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $company->id,
            'user_id' => $finance->id,
            'action' => 'expense.voided',
            'entity_type' => Expense::class,
            'entity_id' => $expense->id,
        ]);
    }

    public function test_finance_can_upload_attachment_and_log_activity(): void
    {
        Storage::fake('public');

        [$company, $department] = $this->createCompanyContext('Expense Attachment');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $expense = $this->createExpense($company, $department, $finance, null);

        $this->actingAs($finance);

        $attachment = app(UploadExpenseAttachment::class)(
            $finance,
            $expense,
            UploadedFile::fake()->create('receipt.pdf', 256, 'application/pdf')
        );

        $this->assertDatabaseHas('expense_attachments', [
            'id' => $attachment->id,
            'company_id' => $company->id,
            'expense_id' => $expense->id,
            'uploaded_by' => $finance->id,
        ]);
        Storage::disk('public')->assertExists($attachment->file_path);
        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $company->id,
            'user_id' => $finance->id,
            'action' => 'expense.attachment.uploaded',
            'entity_type' => Expense::class,
            'entity_id' => $expense->id,
        ]);
    }

    public function test_expense_queries_are_company_scoped(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Expense Scope A');
        [$companyB, $departmentB] = $this->createCompanyContext('Expense Scope B');
        $ownerA = $this->createUser($companyA, $departmentA, UserRole::Owner->value);
        $ownerB = $this->createUser($companyB, $departmentB, UserRole::Owner->value);

        $expenseA = $this->createExpense($companyA, $departmentA, $ownerA, null);
        $expenseB = $this->createExpense($companyB, $departmentB, $ownerB, null);

        $this->actingAs($ownerA);

        $visibleIds = Expense::query()->pluck('id')->all();

        $this->assertContains($expenseA->id, $visibleIds);
        $this->assertNotContains($expenseB->id, $visibleIds);
    }

    public function test_cannot_link_foreign_company_vendor_when_creating_expense(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Expense Vendor Guard A');
        [$companyB, $departmentB] = $this->createCompanyContext('Expense Vendor Guard B');
        $finance = $this->createUser($companyA, $departmentA, UserRole::Finance->value);
        $foreignVendor = $this->createVendor($companyB);

        $this->actingAs($finance);

        $this->expectException(ValidationException::class);

        app(CreateExpense::class)($finance, $this->validExpensePayload($departmentA, $foreignVendor));
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

    private function createVendor(Company $company): Vendor
    {
        return Vendor::query()->create([
            'company_id' => $company->id,
            'name' => 'Vendor '.Str::lower(Str::random(5)),
            'vendor_type' => 'supplier',
            'contact_person' => 'Vendor Contact',
            'phone' => '08000000000',
            'email' => Str::lower(Str::random(5)).'@vendor.test',
            'address' => 'Vendor Address',
            'bank_name' => 'Example Bank',
            'account_name' => 'Vendor Account',
            'account_number' => (string) random_int(10000000, 99999999),
            'notes' => 'Vendor seed',
            'is_active' => true,
        ]);
    }

    private function createExpense(
        Company $company,
        Department $department,
        User $creator,
        ?Vendor $vendor
    ): Expense {
        return Expense::query()->create([
            'company_id' => $company->id,
            'expense_code' => 'FD-EXP-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'department_id' => $department->id,
            'vendor_id' => $vendor?->id,
            'title' => 'Seeded Expense',
            'description' => 'Seeded description',
            'amount' => 45000,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'paid_by_user_id' => $creator->id,
            'created_by' => $creator->id,
            'status' => 'posted',
            'is_direct' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validExpensePayload(Department $department, ?Vendor $vendor): array
    {
        return [
            'department_id' => $department->id,
            'vendor_id' => $vendor?->id,
            'title' => 'Generator fuel payment',
            'description' => 'Fuel top-up payment',
            'amount' => 125000,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'paid_by_user_id' => null,
        ];
    }
}
