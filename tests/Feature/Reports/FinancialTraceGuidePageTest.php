<?php

namespace Tests\Feature\Reports;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Enums\UserRole;
use App\Livewire\Reports\FinancialTraceGuidePage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class FinancialTraceGuidePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_can_view_budget_to_payment_guide(): void
    {
        [$company, $department] = $this->createCompanyContext('Financial Trace Guide');
        $finance = $this->createUser($company, $department, UserRole::Finance->value);

        $this->actingAs($finance)
            ->get(route('reports.financial-trace-help'))
            ->assertOk()
            ->assertSee('Budget to Payment Guide');

        Livewire::test(FinancialTraceGuidePage::class)
            ->assertSee('Trace Sequence')
            ->assertSee('Report Columns')
            ->assertSee('Trace Notes')
            ->assertSee('Where to Fix Issues')
            ->assertSee('Settled without expense');
    }

    public function test_staff_cannot_view_budget_to_payment_guide(): void
    {
        [$company, $department] = $this->createCompanyContext('Financial Trace Guide Staff');
        $staff = $this->createUser($company, $department, UserRole::Staff->value);

        $this->actingAs($staff)
            ->get(route('reports.financial-trace-help'))
            ->assertForbidden();
    }

    /**
     * @return array{0:Company,1:Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+trace-guide@example.test',
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
}
