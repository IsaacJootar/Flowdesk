<?php

namespace Tests\Feature\Expenses;

use App\Actions\Expenses\CreateExpense;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Vendors\Models\Vendor;
use App\Enums\UserRole;
use App\Livewire\Expenses\ExpensesPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ExpenseReceiptAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_agent_analyzes_and_applies_receipt_suggestions(): void
    {
        [$company, $department] = $this->createCompanyContext('Expense Receipt Agent Apply');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $vendor = $this->createVendor($company, 'Acme Fuel');

        $this->actingAs($finance);

        Livewire::test(ExpensesPage::class)
            ->call('openCreateModal')
            ->set('newAttachments', [
                UploadedFile::fake()->create('acme_fuel_2026-03-01_ngn125000_inv-7788.jpg', 120, 'image/jpeg'),
            ])
            ->call('analyzeReceiptAttachments')
            ->assertSet('showReceiptAgentPanel', true)
            ->assertSet('receiptSuggestionFields.amount', 125000)
            ->assertSet('receiptSuggestionFields.expense_date', '2026-03-01')
            ->assertSet('receiptSuggestionFields.vendor_id', (int) $vendor->id)
            ->call('applyReceiptSuggestions')
            ->assertSet('form.amount', '125000')
            ->assertSet('form.expense_date', '2026-03-01')
            ->assertSet('form.vendor_id', (string) $vendor->id);
    }

    public function test_receipt_agent_extracts_plain_amount_from_filename_hints(): void
    {
        [$company, $department] = $this->createCompanyContext('Expense Receipt Agent Amount');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);

        $this->actingAs($finance);

        Livewire::test(ExpensesPage::class)
            ->call('openCreateModal')
            ->set('newAttachments', [
                UploadedFile::fake()->create('random_receipt_total_45750_2026-03-03.jpg', 120, 'image/jpeg'),
            ])
            ->call('analyzeReceiptAttachments')
            ->assertSet('showReceiptAgentPanel', true)
            ->assertSet('receiptSuggestionFields.amount', 45750)
            ->assertSet('receiptSuggestionFields.expense_date', '2026-03-03');
    }

    public function test_receipt_agent_uses_model_output_when_ollama_is_available(): void
    {
        [$company, $department] = $this->createCompanyContext('Expense Receipt Agent Model');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $vendor = $this->createVendor($company, 'Acme Fuel');

        $this->actingAs($finance);

        config([
            'ai.runtime.provider' => 'ollama',
            'ai.runtime.base_url' => 'http://ollama.test',
            'ai.runtime.request_timeout_seconds' => 8,
            'ai.models.primary' => 'qwen2.5:7b-instruct',
        ]);

        Http::fake([
            'http://ollama.test/api/tags' => Http::response(['models' => []], 200),
            'http://ollama.test/api/generate' => Http::response([
                'response' => json_encode([
                    'vendor_name' => 'Acme Fuel',
                    'expense_date' => '2026-03-04',
                    'amount' => 99000,
                    'reference' => 'INV-4432',
                    'category' => 'fuel',
                    'title' => 'Fuel Purchase',
                    'confidence' => 86,
                    'notes' => ['Extracted from receipt body text'],
                ]),
            ], 200),
        ]);

        Livewire::test(ExpensesPage::class)
            ->call('openCreateModal')
            ->set('newAttachments', [
                UploadedFile::fake()->create('receipt_scan.jpg', 120, 'image/jpeg'),
            ])
            ->call('analyzeReceiptAttachments')
            ->assertSet('showReceiptAgentPanel', true)
            ->assertSet('receiptSuggestionFields.vendor_id', (int) $vendor->id)
            ->assertSet('receiptSuggestionFields.amount', 99000)
            ->assertSet('receiptSuggestionFields.expense_date', '2026-03-04')
            ->assertSet('receiptAgentConfidence', 86);
    }

    public function test_duplicate_guard_blocks_save_until_override_is_checked(): void
    {
        [$company, $department] = $this->createCompanyContext('Expense Receipt Agent Duplicate');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);
        $vendor = $this->createVendor($company, 'Soft Duplicate Vendor');

        $this->actingAs($finance);

        app(CreateExpense::class)($finance, [
            'department_id' => (int) $department->id,
            'vendor_id' => (int) $vendor->id,
            'title' => 'Generator fuel refill',
            'description' => 'Existing posted expense',
            'amount' => 45000,
            'expense_date' => '2026-03-01',
            'payment_method' => 'transfer',
            'accounting_category_key' => 'spend_operations',
            'paid_by_user_id' => (int) $finance->id,
        ]);

        Livewire::test(ExpensesPage::class)
            ->call('openCreateModal')
            ->set('form.department_id', (string) $department->id)
            ->set('form.vendor_id', (string) $vendor->id)
            ->set('form.title', 'Generator maintenance charge')
            ->set('form.description', 'Another entry')
            ->set('form.amount', '45000')
            ->set('form.expense_date', '2026-03-01')
            ->set('form.payment_method', 'transfer')
            ->set('form.accounting_category_key', 'spend_operations')
            ->set('form.paid_by_user_id', (string) $finance->id)
            ->call('save')
            ->assertSet('duplicateRisk', 'soft')
            ->assertSet('duplicateOverride', false)
            ->set('duplicateOverride', true)
            ->call('save');

        $this->assertDatabaseCount('expenses', 2);
    }

    public function test_manager_sees_explicit_create_button_diagnostic_reason(): void
    {
        [$company, $department] = $this->createCompanyContext('Expense Receipt Agent Diagnostics');
        $manager = $this->createUser($company, $department, UserRole::Manager->value);

        $this->actingAs($manager);

        Livewire::test(ExpensesPage::class)
            ->assertSee('Read-only access.')
            ->assertSee('Your role is not allowed for this expense action.');
    }

    /**
     * @return array{0:Company,1:Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+expense-receipt-agent@example.test',
            'is_active' => true,
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

    private function createVendor(Company $company, string $name): Vendor
    {
        return Vendor::query()->create([
            'company_id' => $company->id,
            'name' => $name,
            'vendor_type' => 'supplier',
            'contact_person' => 'Vendor Contact',
            'phone' => '08000000000',
            'email' => Str::slug($name).'-'.Str::lower(Str::random(5)).'@vendor.test',
            'address' => 'Vendor Address',
            'bank_name' => 'Example Bank',
            'bank_code' => '999',
            'account_name' => 'Vendor Account',
            'account_number' => (string) random_int(10000000, 99999999),
            'notes' => 'Vendor seed',
            'is_active' => true,
        ]);
    }
}
