<?php

namespace App\Livewire\Organization;

use App\Domains\Approvals\Models\ApprovalWorkflow;
use App\Domains\Company\Models\Department;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Company Administration')]
class OrganizationAdminDeskPage extends Component
{
    private const LANE_LIMIT = 8;

    public bool $readyToLoad = false;

    public string $search = '';

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);
    }

    public function loadData(): void
    {
        $this->readyToLoad = true;
    }

    public function updatedSearch(): void
    {
        $this->search = trim($this->search);
    }

    public function render(): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);

        $desk = $this->readyToLoad
            ? $this->buildDeskData($user)
            : $this->emptyDeskData('Loading organization administration desk...');

        return view('livewire.organization.organization-admin-desk-page', [
            'desk' => $desk,
        ]);
    }

    /**
     * @return array{enabled:bool,disabled_reason:?string,summary:array<string,mixed>,lanes:array<string,array<int,array<string,mixed>>>}
     */
    private function buildDeskData(User $user): array
    {
        $departmentLane = $this->departmentCoverageLane($user);
        $teamLane = $this->teamAssignmentLane($user);
        $workflowLane = $this->workflowGovernanceLane($user);

        $workload = $this->buildWorkloadSummary([
            ['key' => 'department_coverage', 'label' => 'Departments Needing Head', 'count' => $departmentLane['count'], 'tone' => 'sky'],
            ['key' => 'team_assignment', 'label' => 'Team Assignment Gaps', 'count' => $teamLane['count'], 'tone' => 'indigo'],
            ['key' => 'workflow_governance', 'label' => 'Workflow Governance Gaps', 'count' => $workflowLane['count'], 'tone' => 'amber'],
        ]);

        return [
            'enabled' => true,
            'disabled_reason' => null,
            'summary' => [
                'department_coverage' => $departmentLane['count'],
                'team_assignment' => $teamLane['count'],
                'workflow_governance' => $workflowLane['count'],
                ...$workload,
            ],
            'lanes' => [
                'department_coverage' => $departmentLane['rows'],
                'team_assignment' => $teamLane['rows'],
                'workflow_governance' => $workflowLane['rows'],
            ],
        ];
    }

    /**
     * @return array{count:int,rows:array<int,array<string,mixed>>}
     */
    private function departmentCoverageLane(User $user): array
    {
        $query = Department::query()
            ->where('company_id', (int) $user->company_id)
            ->where(function ($builder): void {
                $builder
                    ->whereNull('manager_user_id')
                    ->orWhere('manager_user_id', 0);
            })
            ->withCount('users')
            ->orderBy('name');

        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%');
            });
        }

        $count = (int) (clone $query)->count();

        $rows = (clone $query)
            ->limit(self::LANE_LIMIT)
            ->get(['id', 'name', 'code', 'users_count'])
            ->map(function (Department $department): array {
                return [
                    'ref' => (string) $department->name,
                    'title' => $department->code ? 'Code: '.(string) $department->code : 'No department code set',
                    'meta' => sprintf('Members: %s', number_format((int) $department->users_count)),
                    'status' => 'Head not assigned',
                    'context' => 'Assign a department head so approval routing and accountability stay clear.',
                    'next_action_label' => 'Assign Head',
                    'next_action_url' => route('departments.index', ['search' => (string) $department->name]),
                    'next_action_tone' => 'sky',
                ];
            })
            ->all();

        return [
            'count' => $count,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{count:int,rows:array<int,array<string,mixed>>}
     */
    private function teamAssignmentLane(User $user): array
    {
        $query = User::query()
            ->where('company_id', (int) $user->company_id)
            ->where('is_active', true)
            ->where(function ($builder): void {
                // Ownership and reporting lines must stay explicit for operational handoffs.
                $builder
                    ->whereNull('department_id')
                    ->orWhere(function ($inner): void {
                        $inner
                            ->whereIn('role', [
                                UserRole::Staff->value,
                                UserRole::Manager->value,
                                UserRole::Finance->value,
                                UserRole::Auditor->value,
                            ])
                            ->whereNull('reports_to_user_id');
                    });
            })
            ->with(['department:id,name', 'reportsTo:id,name'])
            ->orderBy('name');

        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        $count = (int) (clone $query)->count();

        $rows = (clone $query)
            ->limit(self::LANE_LIMIT)
            ->get(['id', 'name', 'email', 'role', 'department_id', 'reports_to_user_id'])
            ->map(function (User $subject): array {
                $missingParts = [];
                if (! $subject->department_id) {
                    $missingParts[] = 'department';
                }

                if (
                    in_array((string) $subject->role, [
                        UserRole::Staff->value,
                        UserRole::Manager->value,
                        UserRole::Finance->value,
                        UserRole::Auditor->value,
                    ], true)
                    && ! $subject->reports_to_user_id
                ) {
                    $missingParts[] = 'reports-to line';
                }

                $statusLabel = $missingParts === []
                    ? 'Assignment aligned'
                    : 'Missing '.implode(' + ', $missingParts);

                return [
                    'ref' => (string) $subject->name,
                    'title' => (string) $subject->email,
                    'meta' => sprintf(
                        'Role: %s | Department: %s | Reports to: %s',
                        ucfirst((string) $subject->role),
                        (string) ($subject->department?->name ?? 'Not assigned'),
                        (string) ($subject->reportsTo?->name ?? 'Not assigned')
                    ),
                    'status' => $statusLabel,
                    'context' => 'Complete assignment data to keep ownership and approval escalation consistent.',
                    'next_action_label' => 'Fix Assignment',
                    'next_action_url' => route('team.index', ['search' => (string) $subject->name]),
                    'next_action_tone' => 'indigo',
                ];
            })
            ->all();

        return [
            'count' => $count,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{count:int,rows:array<int,array<string,mixed>>}
     */
    private function workflowGovernanceLane(User $user): array
    {
        $rows = [];

        foreach (ApprovalWorkflow::supportedAppliesTo() as $scope) {
            $scopeLabel = ApprovalWorkflow::labelForAppliesTo($scope);

            $scopeQuery = ApprovalWorkflow::query()
                ->where('company_id', (int) $user->company_id)
                ->where('applies_to', $scope)
                ->where('is_active', true);

            $total = (int) (clone $scopeQuery)->count();
            $hasDefault = (bool) (clone $scopeQuery)->where('is_default', true)->exists();

            if ($total > 0 && $hasDefault) {
                continue;
            }

            if ($this->search !== '') {
                $search = mb_strtolower($this->search);
                if (! str_contains(mb_strtolower($scopeLabel.' workflow'), $search)) {
                    continue;
                }
            }

            $rows[] = [
                'ref' => $scopeLabel,
                'title' => $total === 0 ? 'No active workflows configured' : 'Default workflow missing',
                'meta' => sprintf('Active workflows: %s', number_format($total)),
                'status' => $total === 0 ? 'Setup required' : 'Default required',
                'context' => $total === 0
                    ? 'Create at least one active workflow for this scope.'
                    : 'Set one active default workflow so approvals route predictably.',
                'next_action_label' => $total === 0 ? 'Create Workflow' : 'Set Default',
                'next_action_url' => route('approval-workflows.index', ['workflowScope' => $scope]),
                'next_action_tone' => 'amber',
            ];
        }

        return [
            'count' => count($rows),
            'rows' => array_slice($rows, 0, self::LANE_LIMIT),
        ];
    }

    /**
     * @param  array<int, array{key:string,label:string,count:int,tone:string}>  $segments
     * @return array{workload_total:int,bottleneck_label:string,bottleneck_count:int,segments:array<int,array{key:string,label:string,count:int,percent:float,tone:string}>}
     */
    private function buildWorkloadSummary(array $segments): array
    {
        $workloadTotal = array_sum(array_map(static fn (array $segment): int => (int) ($segment['count'] ?? 0), $segments));

        $bottleneckLabel = 'No blockers';
        $bottleneckCount = 0;

        $normalizedSegments = array_map(function (array $segment) use ($workloadTotal, &$bottleneckLabel, &$bottleneckCount): array {
            $count = (int) ($segment['count'] ?? 0);
            $percent = $workloadTotal > 0
                ? round(($count / $workloadTotal) * 100, 2)
                : 0.0;

            if ($count > $bottleneckCount) {
                $bottleneckCount = $count;
                $bottleneckLabel = (string) ($segment['label'] ?? 'No blockers');
            }

            return [
                'key' => (string) ($segment['key'] ?? 'segment'),
                'label' => (string) ($segment['label'] ?? 'Segment'),
                'count' => $count,
                'percent' => $percent,
                'tone' => (string) ($segment['tone'] ?? 'slate'),
            ];
        }, $segments);

        return [
            'workload_total' => $workloadTotal,
            'bottleneck_label' => $bottleneckLabel,
            'bottleneck_count' => $bottleneckCount,
            'segments' => $normalizedSegments,
        ];
    }

    /**
     * @return array{enabled:bool,disabled_reason:?string,summary:array<string,mixed>,lanes:array<string,array<int,array<string,mixed>>>}
     */
    private function emptyDeskData(string $reason): array
    {
        return [
            'enabled' => false,
            'disabled_reason' => $reason,
            'summary' => [
                'department_coverage' => 0,
                'team_assignment' => 0,
                'workflow_governance' => 0,
                'workload_total' => 0,
                'bottleneck_label' => 'No blockers',
                'bottleneck_count' => 0,
                'segments' => [],
            ],
            'lanes' => [
                'department_coverage' => [],
                'team_assignment' => [],
                'workflow_governance' => [],
            ],
        ];
    }

    private function canAccessPage(User $user): bool
    {
        return (string) $user->role === UserRole::Owner->value;
    }
}
