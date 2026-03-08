<?php

namespace Tests\Feature\Requests;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\UserRole;
use App\Livewire\Requests\RequestReportsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class RequestReportsValidationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_reports_normalizes_invalid_filter_values(): void
    {
        [$company, $department] = $this->createCompanyContext('Request Reports Validation');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        SpendRequest::query()->create([
            'company_id' => $company->id,
            'request_code' => 'REQ-RPT-VAL-001',
            'requested_by' => $owner->id,
            'department_id' => $department->id,
            'title' => 'Validation hardening request',
            'amount' => 150000,
            'currency' => 'NGN',
            'status' => 'approved',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($owner);

        Livewire::test(RequestReportsPage::class)
            ->set('statusFilter', 'DROP TABLE requests')
            ->assertSet('statusFilter', 'all')
            ->set('typeFilter', '<bad>')
            ->assertSet('typeFilter', 'all')
            ->set('departmentFilter', 'abc')
            ->assertSet('departmentFilter', 'all')
            ->set('dateFrom', '2026-13-41')
            ->assertSet('dateFrom', '')
            ->set('dateTo', 'bad-date')
            ->assertSet('dateTo', '')
            ->set('dateFrom', '2026-03-09')
            ->set('dateTo', '2026-03-01')
            ->assertSet('dateTo', '2026-03-09')
            ->set('perPage', 999)
            ->assertSet('perPage', 10)
            ->set('search', str_repeat('X', 220))
            ->assertSet('search', str_repeat('X', 120));
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+request-reports-validation@example.test',
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

