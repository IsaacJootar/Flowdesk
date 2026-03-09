<?php

namespace Tests\Feature\Requests;

use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\TenantFeatureEntitlement;
use App\Domains\Requests\Models\SpendRequest;
use App\Enums\UserRole;
use App\Livewire\Requests\RequestsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class RequestFlowAgentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_flow_agents_button_is_hidden_when_ai_entitlement_is_disabled(): void
    {
        [$company, $department] = $this->createCompanyContext('Flow Agents Disabled');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);

        $this->actingAs($owner);

        Livewire::test(RequestsPage::class)
            ->call('openCreateModal')
            ->assertDontSee('Flow Agents');
    }

    public function test_flow_agents_can_analyze_draft_when_ai_entitlement_is_enabled(): void
    {
        [$company, $department] = $this->createCompanyContext('Flow Agents Draft');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $this->enableAiForCompany($company);

        $this->actingAs($owner);

        Livewire::test(RequestsPage::class)
            ->call('openCreateModal')
            ->assertSee('Flow Agents')
            ->call('runFlowAgentsForDraft')
            ->assertSet('showFlowAgentsPanel', true)
            ->assertSet('flowAgentsContext', 'draft')
            ->assertSee('Missing Title')
            ->assertSee('Workflow Not Selected')
            ->assertSee('Advisory only');
    }

    public function test_flow_agents_can_analyze_selected_request_in_view_modal(): void
    {
        [$company, $department] = $this->createCompanyContext('Flow Agents View');
        $owner = $this->createUser($company, $department, UserRole::Owner->value);
        $this->enableAiForCompany($company);

        $request = SpendRequest::query()->create([
            'company_id' => $company->id,
            'request_code' => 'FD-REQ-FA-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'requested_by' => $owner->id,
            'department_id' => $department->id,
            'title' => 'Returned Request For Flow Agent',
            'description' => 'Request seeded for Flow Agents view analysis.',
            'amount' => 50000,
            'currency' => 'NGN',
            'status' => 'returned',
            'metadata' => [
                'type' => 'spend',
                'request_type_name' => 'Spend',
            ],
        ]);

        $this->actingAs($owner);

        Livewire::test(RequestsPage::class)
            ->call('openViewModal', (int) $request->id)
            ->call('runFlowAgentsForSelectedRequest')
            ->assertSet('showFlowAgentsPanel', true)
            ->assertSet('flowAgentsContext', 'view')
            ->assertSee('Returned For Changes')
            ->assertSee('No Attachments')
            ->assertDontSee('Approval Action Available')
            ->assertDontSee('Run Flow Agent Approve')
            ->assertDontSee('Submit For Approval')
            ->assertDontSee('Run Flow Agent Submit');
    }

    /**
     * @return array{0:Company,1:Department}
     */
    private function createCompanyContext(string $name): array
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'+flow-agents@example.test',
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

    private function enableAiForCompany(Company $company): void
    {
        TenantFeatureEntitlement::query()->create([
            'company_id' => (int) $company->id,
            'ai_enabled' => true,
        ]);
    }
}
