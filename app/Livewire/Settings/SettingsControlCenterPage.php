<?php

namespace App\Livewire\Settings;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\TenantModuleAccessService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Settings Control Center')]
class SettingsControlCenterPage extends Component
{
    /**
     * @var array<int, string>
     */
    private const SECTION_ORDER = [
        'organization',
        'requests',
        'controls',
        'security',
    ];

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);
    }

    public function render(TenantModuleAccessService $moduleAccessService): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $this->canAccessPage($user), 403);

        $moduleFlags = [
            'requests' => $moduleAccessService->moduleEnabled($user, 'requests'),
            'communications' => $moduleAccessService->moduleEnabled($user, 'communications'),
            'expenses' => $moduleAccessService->moduleEnabled($user, 'expenses'),
            'vendors' => $moduleAccessService->moduleEnabled($user, 'vendors'),
            'assets' => $moduleAccessService->moduleEnabled($user, 'assets'),
            'procurement' => $moduleAccessService->moduleEnabled($user, 'procurement'),
            'treasury' => $moduleAccessService->moduleEnabled($user, 'treasury'),
            'fintech' => $moduleAccessService->moduleEnabled($user, 'fintech'),
        ];

        $sectionMeta = [
            'organization' => [
                'label' => 'Organization Settings',
                'description' => 'Tenant structure, teams, and approval ownership.',
                'tone' => 'sky',
            ],
            'requests' => [
                'label' => 'Request Settings',
                'description' => 'Request policies, timing rules, and communications behavior.',
                'tone' => 'indigo',
            ],
            'controls' => [
                'label' => 'Module Controls',
                'description' => 'Operational guardrails for finance and operations modules.',
                'tone' => 'amber',
            ],
            'security' => [
                'label' => 'Security & Access',
                'description' => 'Profile and access-level account settings.',
                'tone' => 'rose',
            ],
        ];

        $sections = [
            'organization' => [
                [
                    'label' => 'Company Setup',
                    'description' => 'Company profile, baseline metadata, and core workspace identity.',
                    'route' => 'settings.company.setup',
                    'module' => null,
                ],
                [
                    'label' => 'Departments',
                    'description' => 'Department structure and ownership scope.',
                    'route' => 'departments.index',
                    'module' => null,
                ],
                [
                    'label' => 'Team',
                    'description' => 'Role assignment, reporting lines, and user lifecycle controls.',
                    'route' => 'team.index',
                    'module' => null,
                ],
                [
                    'label' => 'Approval Workflows',
                    'description' => 'Approval chain structure and ordering rules.',
                    'route' => 'approval-workflows.index',
                    'module' => 'requests',
                ],
            ],
            'requests' => [
                [
                    'label' => 'Request Configuration',
                    'description' => 'Request types, spend categories, and request policy scaffolding.',
                    'route' => 'settings.request-configuration',
                    'module' => 'requests',
                ],
                [
                    'label' => 'Approval Timing Controls',
                    'description' => 'SLA windows, reminder cadence, and escalation timing policies.',
                    'route' => 'settings.approval-timing-controls',
                    'module' => 'requests',
                ],
                [
                    'label' => 'Communications',
                    'description' => 'In-app/email/sms channel enablement and fallback behavior.',
                    'route' => 'settings.communications',
                    'module' => 'communications',
                ],
            ],
            'controls' => [
                [
                    'label' => 'Expense Controls',
                    'description' => 'Posting/edit/void guardrails and maker-checker policy.',
                    'route' => 'settings.expense-controls',
                    'module' => 'expenses',
                ],
                [
                    'label' => 'Vendor Controls',
                    'description' => 'Vendor profile and payables action guardrails.',
                    'route' => 'settings.vendor-controls',
                    'module' => 'vendors',
                ],
                [
                    'label' => 'Asset Controls',
                    'description' => 'Asset registration, assignment, maintenance, and disposal policy.',
                    'route' => 'settings.asset-controls',
                    'module' => 'assets',
                ],
                [
                    'label' => 'Procurement Controls',
                    'description' => 'PO conversion requirements and procurement release guardrails.',
                    'route' => 'settings.procurement-controls',
                    'module' => 'procurement',
                ],
                [
                    'label' => 'Treasury Controls',
                    'description' => 'Reconciliation thresholds, tolerance, and treasury safety checks.',
                    'route' => 'settings.treasury-controls',
                    'module' => 'treasury',
                ],
                [
                    'label' => 'Payments Rails Integration',
                    'description' => 'Business-level provider connection status and tenant rail readiness.',
                    'route' => 'settings.payments-rails',
                    'module' => 'fintech',
                ],
            ],
            'security' => [
                [
                    'label' => 'Profile & Access',
                    'description' => 'Your account profile, authentication, and credential updates.',
                    'route' => 'profile.edit',
                    'module' => null,
                ],
            ],
        ];

        $resolvedSections = [];
        $totalControls = 0;
        $enabledControls = 0;

        foreach (self::SECTION_ORDER as $key) {
            $rows = $sections[$key] ?? [];
            $cards = [];

            foreach ($rows as $row) {
                $moduleKey = $row['module'] ?? null;
                $enabled = $moduleKey === null ? true : (bool) ($moduleFlags[$moduleKey] ?? false);

                $cards[] = [
                    'label' => (string) ($row['label'] ?? ''),
                    'description' => (string) ($row['description'] ?? ''),
                    'route' => (string) ($row['route'] ?? ''),
                    'enabled' => $enabled,
                    'status_label' => $enabled ? 'Enabled' : 'Disabled by plan',
                    'action_label' => $enabled ? 'Open' : 'Blocked',
                ];

                $totalControls++;
                if ($enabled) {
                    $enabledControls++;
                }
            }

            $meta = $sectionMeta[$key] ?? [
                'label' => ucfirst($key),
                'description' => 'Settings section.',
                'tone' => 'slate',
            ];

            $resolvedSections[] = [
                'key' => $key,
                'label' => (string) ($meta['label'] ?? ucfirst($key)),
                'description' => (string) ($meta['description'] ?? ''),
                'tone' => (string) ($meta['tone'] ?? 'slate'),
                'cards' => $cards,
            ];
        }

        return view('livewire.settings.settings-control-center-page', [
            'moduleFlags' => $moduleFlags,
            'sections' => $resolvedSections,
            'totalControls' => $totalControls,
            'enabledControls' => $enabledControls,
            'blockedControls' => max(0, $totalControls - $enabledControls),
        ]);
    }

    private function canAccessPage(User $user): bool
    {
        return (string) $user->role === UserRole::Owner->value;
    }
}


