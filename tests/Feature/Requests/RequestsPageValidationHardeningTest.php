<?php

namespace Tests\Feature\Requests;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Requests\Models\CompanyRequestType;
use App\Enums\UserRole;
use App\Livewire\Requests\RequestsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class RequestsPageValidationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_page_normalizes_tampered_filters(): void
    {
        [$company, $department] = $this->createCompanyContext('Requests Filter Harden');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        CompanyRequestType::query()->create([
            'company_id' => $company->id,
            'name' => 'Operations',
            'code' => 'operations',
            'description' => 'Operational requests',
            'is_active' => true,
            'requires_amount' => true,
            'requires_line_items' => true,
            'requires_date_range' => false,
            'requires_vendor' => false,
            'requires_attachments' => false,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($owner);

        Livewire::test(RequestsPage::class)
            ->set('search', '  REQ-1001  ')
            ->assertSet('search', 'REQ-1001')
            ->set('statusFilter', 'not-valid-status')
            ->assertSet('statusFilter', 'all')
            ->set('typeFilter', 'UNKNOWN_TYPE')
            ->assertSet('typeFilter', 'all')
            ->set('departmentFilter', '-4')
            ->assertSet('departmentFilter', 'all')
            ->set('scopeFilter', 'outside_scope')
            ->assertSet('scopeFilter', 'all')
            ->set('dateFrom', '2026/03/01')
            ->assertSet('dateFrom', '')
            ->set('dateTo', '2026-99-99')
            ->assertSet('dateTo', '')
            ->set('dateFrom', '2026-03-10')
            ->set('dateTo', '2026-03-01')
            ->assertSet('dateTo', '')
            ->set('perPage', 999)
            ->assertSet('perPage', 10);
    }

    /**
     * @return array{0:Company,1:Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+requests-page@example.test',
            'is_active' => true,
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

