<?php

namespace Tests\Feature\Organization;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Enums\UserRole;
use App\Livewire\Assets\AssetReportsPage;
use App\Livewire\Assets\AssetsPage;
use App\Livewire\Organization\ApprovalWorkflowsPage;
use App\Livewire\Organization\DepartmentsPage;
use App\Livewire\Requests\RequestsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TenantUserListScopeRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_components_only_render_same_company_user_options(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Scope Regression A');
        [$companyB, $departmentB] = $this->createCompanyContext('Scope Regression B');

        $ownerA = $this->createUser($companyA, $departmentA, UserRole::Owner->value, [
            'name' => 'Owner A Scope',
            'email' => 'owner-a-scope@example.test',
        ]);
        $memberA = $this->createUser($companyA, $departmentA, UserRole::Staff->value, [
            'name' => 'Member A Scope',
            'email' => 'member-a-scope@example.test',
        ]);
        $foreignUser = $this->createUser($companyB, $departmentB, UserRole::Owner->value, [
            'name' => 'Foreign Scope User',
            'email' => 'foreign-scope-user@example.test',
        ]);

        $this->actingAs($ownerA);

        Livewire::test(DepartmentsPage::class)
            ->assertViewHas('managerOptions', fn ($users): bool => $this->containsOnlyCompanyUsers($users, [$ownerA->id, $memberA->id], [$foreignUser->id]));

        Livewire::test(ApprovalWorkflowsPage::class)
            ->assertViewHas('users', fn ($users): bool => $this->containsOnlyCompanyUsers($users, [$ownerA->id, $memberA->id], [$foreignUser->id]));

        Livewire::test(AssetsPage::class)
            ->assertViewHas('assignees', fn ($users): bool => $this->containsOnlyCompanyUsers($users, [$ownerA->id, $memberA->id], [$foreignUser->id]));

        Livewire::test(AssetReportsPage::class)
            ->assertViewHas('assignees', fn ($users): bool => $this->containsOnlyCompanyUsers($users, [$ownerA->id, $memberA->id], [$foreignUser->id]));

        Livewire::test(RequestsPage::class)
            ->assertViewHas('users', fn ($users): bool => $this->containsOnlyCompanyUsers($users, [$ownerA->id, $memberA->id], [$foreignUser->id]));
    }

    /**
     * @param  iterable<mixed>  $users
     * @param  array<int, int>  $requiredIds
     * @param  array<int, int>  $forbiddenIds
     */
    private function containsOnlyCompanyUsers(iterable $users, array $requiredIds, array $forbiddenIds): bool
    {
        $ids = collect($users)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($requiredIds as $requiredId) {
            if (! in_array($requiredId, $ids, true)) {
                return false;
            }
        }

        foreach ($forbiddenIds as $forbiddenId) {
            if (in_array($forbiddenId, $ids, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{0: Company, 1: Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+company@example.test',
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
