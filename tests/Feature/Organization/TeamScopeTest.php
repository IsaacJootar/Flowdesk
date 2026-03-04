<?php

namespace Tests\Feature\Organization;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Enums\UserRole;
use App\Livewire\Organization\TeamPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TeamScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_page_only_shows_current_company_users_and_manager_options(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Team Scope A');
        [$companyB, $departmentB] = $this->createCompanyContext('Team Scope B');

        $ownerA = $this->createUser($companyA, $departmentA, UserRole::Owner->value, [
            'name' => 'Owner A',
            'email' => 'owner-a@example.test',
        ]);
        $this->createUser($companyA, $departmentA, UserRole::Staff->value, [
            'name' => 'Team A Staff',
            'email' => 'team-a-staff@example.test',
        ]);

        $this->createUser($companyB, $departmentB, UserRole::Owner->value, [
            'name' => 'Owner B',
            'email' => 'owner-b@example.test',
        ]);
        $this->createUser($companyB, $departmentB, UserRole::Staff->value, [
            'name' => 'Team B Staff',
            'email' => 'team-b-staff@example.test',
        ]);

        $this->actingAs($ownerA)
            ->get(route('team.index'))
            ->assertOk()
            ->assertSee('Owner A')
            ->assertSee('Team A Staff')
            ->assertDontSee('Team B Staff')
            ->assertDontSee('owner-b@example.test');
    }

    public function test_team_page_sanitizes_invalid_cross_company_reports_to_before_save(): void
    {
        [$companyA, $departmentA] = $this->createCompanyContext('Team Scope C');
        [$companyB, $departmentB] = $this->createCompanyContext('Team Scope D');

        $ownerA = $this->createUser($companyA, $departmentA, UserRole::Owner->value, [
            'name' => 'Owner Scope A',
            'email' => 'owner-scope-a@example.test',
        ]);
        $staffA = $this->createUser($companyA, $departmentA, UserRole::Staff->value, [
            'name' => 'Staff Scope A',
            'email' => 'staff-scope-a@example.test',
        ]);
        $foreignManager = $this->createUser($companyB, $departmentB, UserRole::Manager->value, [
            'name' => 'Foreign Manager',
            'email' => 'foreign-manager@example.test',
        ]);

        $staffA->forceFill([
            'reports_to_user_id' => $foreignManager->id,
        ])->save();

        $this->actingAs($ownerA);

        $reportsToPath = 'userAssignments.'.$staffA->id.'.reports_to_user_id';

        Livewire::test(TeamPage::class)
            ->assertSet($reportsToPath, '')
            ->call('saveUserAssignment', $staffA->id)
            ->assertSet('feedbackError', null)
            ->assertSet('feedbackMessage', 'User assignment updated.');

        $this->assertDatabaseHas('users', [
            'id' => $staffA->id,
            'reports_to_user_id' => null,
        ]);
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
