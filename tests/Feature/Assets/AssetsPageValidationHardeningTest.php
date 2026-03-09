<?php

namespace Tests\Feature\Assets;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Enums\UserRole;
use App\Livewire\Assets\AssetsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AssetsPageValidationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_assets_page_normalizes_tampered_filters(): void
    {
        [$company, $department] = $this->createCompanyContext('Asset Filter Harden');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $this->actingAs($owner);

        Livewire::test(AssetsPage::class)
            ->set('search', '  Router-01  ')
            ->assertSet('search', 'Router-01')
            ->set('statusFilter', 'not-a-status')
            ->assertSet('statusFilter', 'all')
            ->set('statusFilter', 'DISPOSED')
            ->assertSet('statusFilter', 'disposed')
            ->set('categoryFilter', 'bad-id')
            ->assertSet('categoryFilter', 'all')
            ->set('categoryFilter', '-15')
            ->assertSet('categoryFilter', 'all')
            ->set('assignmentFilter', 'unexpected')
            ->assertSet('assignmentFilter', 'all')
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
            'email' => Str::slug($name).'+asset-page@example.test',
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

