<?php

namespace Database\Seeders;

use App\Domains\Approvals\Models\ApprovalWorkflow;
use App\Domains\Approvals\Models\ApprovalWorkflowStep;
use App\Domains\Approvals\Models\CompanyApprovalTimingSetting;
use App\Domains\Assets\Models\Asset;
use App\Domains\Assets\Models\AssetCategory;
use App\Domains\Assets\Models\AssetCommunicationLog;
use App\Domains\Assets\Models\AssetEvent;
use App\Domains\Assets\Models\CompanyAssetPolicySetting;
use App\Domains\Audit\Models\ActivityLog;
use App\Domains\Budgets\Models\DepartmentBudget;
use App\Domains\Company\Models\Company;
use App\Domains\Company\Models\CompanyCommunicationSetting;
use App\Domains\Company\Models\Department;
use App\Domains\Company\Models\ExecutionWebhookEvent;
use App\Domains\Company\Models\TenantAuditEvent;
use App\Domains\Company\Models\TenantFeatureEntitlement;
use App\Domains\Company\Models\TenantPilotKpiCapture;
use App\Domains\Company\Models\TenantPilotWaveOutcome;
use App\Domains\Company\Models\TenantPlanChangeHistory;
use App\Domains\Company\Models\TenantSubscription;
use App\Domains\Company\Models\TenantSubscriptionBillingAttempt;
use App\Domains\Company\Models\TenantUsageCounter;
use App\Domains\Expenses\Models\CompanyExpensePolicySetting;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Procurement\Models\CompanyProcurementControlSetting;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\GoodsReceiptItem;
use App\Domains\Procurement\Models\InvoiceMatchException;
use App\Domains\Procurement\Models\InvoiceMatchResult;
use App\Domains\Procurement\Models\ProcurementCommitment;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseOrderItem;
use App\Domains\Requests\Models\CompanyRequestPolicySetting;
use App\Domains\Requests\Models\CompanyRequestType;
use App\Domains\Requests\Models\CompanySpendCategory;
use App\Domains\Requests\Models\RequestComment;
use App\Domains\Requests\Models\RequestCommunicationLog;
use App\Domains\Requests\Models\RequestItem;
use App\Domains\Requests\Models\RequestPayoutExecutionAttempt;
use App\Domains\Requests\Models\SpendRequest;
use App\Domains\Treasury\Models\BankAccount;
use App\Domains\Treasury\Models\BankStatement;
use App\Domains\Treasury\Models\BankStatementLine;
use App\Domains\Treasury\Models\CompanyTreasuryControlSetting;
use App\Domains\Treasury\Models\PaymentRun;
use App\Domains\Treasury\Models\PaymentRunItem;
use App\Domains\Treasury\Models\ReconciliationException;
use App\Domains\Treasury\Models\ReconciliationMatch;
use App\Domains\Vendors\Models\CompanyVendorPolicySetting;
use App\Domains\Vendors\Models\Vendor;
use App\Domains\Vendors\Models\VendorCommunicationLog;
use App\Domains\Vendors\Models\VendorInvoice;
use App\Domains\Vendors\Models\VendorInvoicePayment;
use App\Enums\PlatformUserRole;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\TenantExecutionModeService;
use App\Services\TenantPlanDefaultsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FlowdeskComprehensiveDemoSeeder extends Seeder
{
    private const PASSWORD = 'Flowdesk@123';

    private TenantPlanDefaultsService $planDefaults;

    public function run(): void
    {
        $this->planDefaults = app(TenantPlanDefaultsService::class);
        $this->seedPlatformUsers();

        $tenants = [
            ['name' => 'Apex Manufacturing Ltd', 'slug' => 'apex-manufacturing', 'industry' => 'Manufacturing', 'plan' => 'pilot', 'subscription' => 'current', 'mode' => TenantExecutionModeService::MODE_DECISION_ONLY, 'provider' => null, 'procurement' => false, 'treasury' => false, 'outcome' => TenantPilotWaveOutcome::OUTCOME_HOLD],
            ['name' => 'Crestline Logistics', 'slug' => 'crestline-logistics', 'industry' => 'Logistics', 'plan' => 'growth', 'subscription' => 'current', 'mode' => TenantExecutionModeService::MODE_DECISION_ONLY, 'provider' => null, 'procurement' => true, 'treasury' => false, 'outcome' => TenantPilotWaveOutcome::OUTCOME_GO],
            ['name' => 'Northwind Health Services', 'slug' => 'northwind-health', 'industry' => 'Healthcare', 'plan' => 'business', 'subscription' => 'current', 'mode' => TenantExecutionModeService::MODE_EXECUTION_ENABLED, 'provider' => 'paystack', 'procurement' => true, 'treasury' => true, 'outcome' => TenantPilotWaveOutcome::OUTCOME_GO],
            ['name' => 'Greenfield Retail Group', 'slug' => 'greenfield-retail', 'industry' => 'Retail', 'plan' => 'enterprise', 'subscription' => 'current', 'mode' => TenantExecutionModeService::MODE_EXECUTION_ENABLED, 'provider' => 'flutterwave', 'procurement' => true, 'treasury' => true, 'outcome' => TenantPilotWaveOutcome::OUTCOME_GO],
            ['name' => 'Horizon Education Trust', 'slug' => 'horizon-education', 'industry' => 'Education', 'plan' => 'business', 'subscription' => 'grace', 'mode' => TenantExecutionModeService::MODE_DECISION_ONLY, 'provider' => null, 'procurement' => true, 'treasury' => true, 'outcome' => TenantPilotWaveOutcome::OUTCOME_NO_GO],
        ];

        foreach ($tenants as $index => $tenant) {
            $this->seedTenant($tenant, $index + 1);
        }

        $this->command?->info('Flowdesk comprehensive demo seeding complete.');
    }

    private function seedPlatformUsers(): void
    {
        foreach ([PlatformUserRole::PlatformOwner->value, PlatformUserRole::PlatformBillingAdmin->value, PlatformUserRole::PlatformOpsAdmin->value] as $platformRole) {
            for ($i = 1; $i <= 3; $i++) {
                User::query()->create([
                    'name' => Str::title(str_replace('_', ' ', $platformRole)).' '.$i,
                    'email' => sprintf('%s.%d@flowdesk.local', $platformRole, $i),
                    'email_verified_at' => now(),
                    'password' => Hash::make(self::PASSWORD),
                    'role' => UserRole::Owner->value,
                    'platform_role' => $platformRole,
                    'is_active' => true,
                    'phone' => sprintf('+234800000%04d', $i),
                    'gender' => $i % 2 === 0 ? 'female' : 'male',
                ]);
            }
        }
    }

    /** @param array<string,mixed> $tenant */
    private function seedTenant(array $tenant, int $index): void
    {
        $company = Company::query()->create([
            'name' => (string) $tenant['name'],
            'slug' => (string) $tenant['slug'],
            'email' => 'ops@'.$tenant['slug'].'.local',
            'phone' => sprintf('+234810%06d', $index),
            'industry' => (string) $tenant['industry'],
            'currency_code' => 'NGN',
            'timezone' => 'Africa/Lagos',
            'address' => 'Seeded organization address',
            'is_active' => true,
            'lifecycle_status' => 'active',
            'status_reason' => 'seeded',
            'status_updated_at' => now(),
        ]);

        $departments = $this->createDepartments($company);
        $users = $this->createUsers($company, $departments);
        $owner = $users[UserRole::Owner->value][0];
        $finance = $users[UserRole::Finance->value][0];

        $company->forceFill(['created_by' => $owner->id, 'updated_by' => $owner->id])->save();

        $this->seedPolicyAndSubscription($company, $owner, $tenant);
        $workflows = $this->seedWorkflows($company, $owner);
        $vendors = $this->seedVendors($company);
        $budgets = $this->seedBudgets($company, $departments, $owner);
        $requests = $this->seedRequests($company, $users, $departments, $vendors, $workflows);
        $expenses = $this->seedExpenses($company, $users, $departments, $vendors, $requests);
        $vendorFinance = $this->seedVendorFinance($company, $finance, $vendors);
        $this->seedProcurementAndTreasury($company, $users, $requests, $budgets, $vendorFinance, $expenses);
        $this->seedAssets($company, $users, $departments);
        $this->seedExecution($company, $users, $requests);
        $this->seedAuditAndPilot($company, $users, $tenant, $requests, $expenses, $vendors);
    }

    /** @return array<string,Department> */
    private function createDepartments(Company $company): array
    {
        $defs = ['general' => ['General', 'GEN'], 'finance' => ['Finance', 'FIN'], 'operations' => ['Operations', 'OPS'], 'procurement' => ['Procurement', 'PRC'], 'it' => ['IT', 'IT'], 'hr' => ['HR', 'HR']];
        $departments = [];

        foreach ($defs as $key => $def) {
            $departments[$key] = Department::query()->create([
                'company_id' => $company->id,
                'name' => $def[0],
                'code' => $def[1],
                'is_active' => true,
            ]);
        }

        return $departments;
    }

    /** @param array<string,Department> $departments @return array<string,array<int,User>> */
    private function createUsers(Company $company, array $departments): array
    {
        $roles = [UserRole::Owner->value, UserRole::Finance->value, UserRole::Manager->value, UserRole::Staff->value, UserRole::Auditor->value];
        $users = [];
        $tag = Str::before((string) $company->slug, '-');

        foreach ($roles as $role) {
            $users[$role] = [];
            for ($i = 1; $i <= 3; $i++) {
                $dept = match ($role) {
                    UserRole::Owner->value => 'general',
                    UserRole::Finance->value, UserRole::Auditor->value => 'finance',
                    UserRole::Manager->value => ['operations', 'procurement', 'it'][$i - 1],
                    default => ['operations', 'procurement', 'hr'][$i - 1],
                };

                $users[$role][] = User::query()->create([
                    'company_id' => $company->id,
                    'department_id' => $departments[$dept]->id,
                    'name' => Str::title($tag).' '.Str::title($role).' '.$i,
                    'email' => sprintf('%s.%s%d@flowdesk.local', $tag, $role, $i),
                    'email_verified_at' => now(),
                    'password' => Hash::make(self::PASSWORD),
                    'role' => $role,
                    'is_active' => true,
                    'phone' => sprintf('+234811%06d', ($company->id * 100) + $i),
                    'gender' => $i % 2 === 0 ? 'female' : 'male',
                    'last_login_at' => now()->subDays(rand(0, 7)),
                ]);
            }
        }

        foreach ($users[UserRole::Staff->value] as $staff) {
            $staff->forceFill(['reports_to_user_id' => $users[UserRole::Manager->value][0]->id])->save();
        }

        foreach ([UserRole::Finance->value, UserRole::Manager->value, UserRole::Auditor->value] as $role) {
            foreach ($users[$role] as $user) {
                $user->forceFill(['reports_to_user_id' => $users[UserRole::Owner->value][0]->id])->save();
            }
        }

        $departments['general']->forceFill(['manager_user_id' => $users[UserRole::Owner->value][0]->id])->save();
        $departments['finance']->forceFill(['manager_user_id' => $users[UserRole::Finance->value][0]->id])->save();
        $departments['operations']->forceFill(['manager_user_id' => $users[UserRole::Manager->value][0]->id])->save();
        $departments['procurement']->forceFill(['manager_user_id' => $users[UserRole::Manager->value][1]->id])->save();
        $departments['it']->forceFill(['manager_user_id' => $users[UserRole::Manager->value][2]->id])->save();
        $departments['hr']->forceFill(['manager_user_id' => $users[UserRole::Manager->value][0]->id])->save();

        return $users;
    }

    /** @return array<string,mixed> */
    private function tenantSeedProfile(Company $company): array
    {
        $tag = Str::before((string) $company->slug, '-');
        $hash = abs(crc32((string) $company->slug));
        $offset = ($hash % 5) + 1;
        $multiplier = 0.85 + (($hash % 25) / 100);
        $countShift = $hash % 3;
        $cities = ['Lagos', 'Abuja', 'Port Harcourt', 'Ibadan', 'Kano', 'Enugu'];
        $city = $cities[$hash % count($cities)];

        return [
            'tag' => $tag,
            'hash' => $hash,
            'offset' => $offset,
            'multiplier' => $multiplier,
            'count_shift' => $countShift,
            'city' => $city,
        ];
    }
    /** @param array<string,mixed> $tenant */
    private function seedPolicyAndSubscription(Company $company, User $owner, array $tenant): void
    {
        CompanyCommunicationSetting::query()->create([
            'company_id' => $company->id,
            'in_app_enabled' => true,
            'email_enabled' => true,
            'sms_enabled' => false,
            'email_configured' => true,
            'sms_configured' => false,
            'fallback_order' => ['in_app', 'email', 'sms'],
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        CompanyRequestPolicySetting::query()->create([
            'company_id' => $company->id,
            'budget_guardrail_mode' => 'warn',
            'duplicate_detection_enabled' => true,
            'duplicate_window_days' => 30,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        CompanyExpensePolicySetting::query()->create([
            'company_id' => $company->id,
            'action_policies' => CompanyExpensePolicySetting::defaultActionPolicies(),
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        CompanyVendorPolicySetting::query()->create([
            'company_id' => $company->id,
            'action_policies' => CompanyVendorPolicySetting::defaultActionPolicies(),
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        CompanyAssetPolicySetting::query()->create([
            'company_id' => $company->id,
            'action_policies' => CompanyAssetPolicySetting::defaultActionPolicies(),
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        CompanyProcurementControlSetting::query()->create([
            'company_id' => $company->id,
            'controls' => CompanyProcurementControlSetting::defaultControls(),
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        CompanyTreasuryControlSetting::query()->create([
            'company_id' => $company->id,
            'controls' => CompanyTreasuryControlSetting::defaultControls(),
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        CompanyApprovalTimingSetting::query()->create([
            'company_id' => $company->id,
            'step_due_hours' => 24,
            'reminder_hours_before_due' => 6,
            'escalation_grace_hours' => 6,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        foreach ([['Spend', 'spend'], ['Travel', 'travel'], ['Leave', 'leave'], ['Procurement', 'procurement']] as $type) {
            CompanyRequestType::query()->create([
                'company_id' => $company->id,
                'name' => $type[0],
                'code' => $type[1],
                'is_active' => true,
                'requires_amount' => true,
                'requires_line_items' => in_array($type[1], ['spend', 'procurement'], true),
                'created_by' => $owner->id,
                'updated_by' => $owner->id,
            ]);
        }

        foreach (['operations', 'travel', 'utilities', 'software', 'procurement', 'maintenance', 'training'] as $category) {
            CompanySpendCategory::query()->create([
                'company_id' => $company->id,
                'name' => Str::title($category),
                'code' => $category,
                'is_active' => true,
                'created_by' => $owner->id,
                'updated_by' => $owner->id,
            ]);
        }

        $subscription = TenantSubscription::query()->create([
            'company_id' => $company->id,
            'plan_code' => (string) $tenant['plan'],
            'subscription_status' => (string) $tenant['subscription'],
            'payment_execution_mode' => (string) $tenant['mode'],
            'starts_at' => now()->subMonths(2)->toDateString(),
            'ends_at' => now()->addMonths(10)->toDateString(),
            'grace_until' => $tenant['subscription'] === 'grace' ? now()->addDays(5)->toDateString() : null,
            'trial_started_at' => now()->subDays(10),
            'trial_ends_at' => now()->addDays(4),
            'seat_limit' => $tenant['plan'] === 'enterprise' ? 300 : ($tenant['plan'] === 'business' ? 120 : 60),
            'execution_provider' => $tenant['provider'],
            'execution_enabled_at' => $tenant['mode'] === TenantExecutionModeService::MODE_EXECUTION_ENABLED ? now()->subDays(15) : null,
            'execution_enabled_by' => $tenant['mode'] === TenantExecutionModeService::MODE_EXECUTION_ENABLED ? $owner->id : null,
            'execution_max_transaction_amount' => $tenant['mode'] === TenantExecutionModeService::MODE_EXECUTION_ENABLED ? 2000000 : null,
            'execution_daily_cap_amount' => $tenant['mode'] === TenantExecutionModeService::MODE_EXECUTION_ENABLED ? 5000000 : null,
            'execution_monthly_cap_amount' => $tenant['mode'] === TenantExecutionModeService::MODE_EXECUTION_ENABLED ? 90000000 : null,
            'execution_maker_checker_threshold_amount' => $tenant['mode'] === TenantExecutionModeService::MODE_EXECUTION_ENABLED ? 350000 : null,
            'execution_allowed_channels' => $tenant['mode'] === TenantExecutionModeService::MODE_EXECUTION_ENABLED ? ['bank_transfer', 'wallet_payout'] : ['bank_transfer'],
            'execution_policy_notes' => $tenant['mode'] === TenantExecutionModeService::MODE_EXECUTION_ENABLED ? 'execution_enabled' : 'decision_only',
            'billing_reference' => 'SUB-'.strtoupper(Str::random(8)),
            'notes' => 'seeded',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $defaults = $this->planDefaults->formEntitlementsForPlan((string) $tenant['plan']);
        TenantFeatureEntitlement::query()->create(array_merge($defaults, [
            'company_id' => $company->id,
            'procurement_enabled' => (bool) $tenant['procurement'],
            'treasury_enabled' => (bool) $tenant['treasury'],
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]));

        TenantPlanChangeHistory::query()->create([
            'company_id' => $company->id,
            'tenant_subscription_id' => $subscription->id,
            'previous_plan_code' => 'pilot',
            'new_plan_code' => (string) $tenant['plan'],
            'previous_subscription_status' => 'current',
            'new_subscription_status' => (string) $tenant['subscription'],
            'changed_at' => now()->subMonths(1),
            'reason' => 'seeded',
            'changed_by' => $owner->id,
        ]);
    }

    /** @return array<string,mixed> */
    private function seedWorkflows(Company $company, User $owner): array
    {
        $requestWorkflow = ApprovalWorkflow::query()->create([
            'company_id' => $company->id,
            'name' => 'Default Spend Approval',
            'code' => 'default_spend',
            'applies_to' => ApprovalWorkflow::APPLIES_TO_REQUEST,
            'is_active' => true,
            'is_default' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $payWorkflow = ApprovalWorkflow::query()->create([
            'company_id' => $company->id,
            'name' => 'Payment Authorization',
            'code' => 'payment_auth',
            'applies_to' => ApprovalWorkflow::APPLIES_TO_PAYMENT_AUTHORIZATION,
            'is_active' => true,
            'is_default' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        ApprovalWorkflowStep::query()->create(['company_id' => $company->id, 'workflow_id' => $requestWorkflow->id, 'step_order' => 1, 'step_key' => 'reports_to', 'actor_type' => 'reports_to', 'notification_channels' => ['in_app'], 'is_active' => true]);
        ApprovalWorkflowStep::query()->create(['company_id' => $company->id, 'workflow_id' => $requestWorkflow->id, 'step_order' => 2, 'step_key' => 'finance', 'actor_type' => 'role', 'actor_value' => UserRole::Finance->value, 'notification_channels' => ['in_app', 'email'], 'min_amount' => 100000, 'is_active' => true]);
        ApprovalWorkflowStep::query()->create(['company_id' => $company->id, 'workflow_id' => $requestWorkflow->id, 'step_order' => 3, 'step_key' => 'owner', 'actor_type' => 'role', 'actor_value' => UserRole::Owner->value, 'notification_channels' => ['in_app', 'email'], 'min_amount' => 400000, 'is_active' => true]);
        ApprovalWorkflowStep::query()->create(['company_id' => $company->id, 'workflow_id' => $payWorkflow->id, 'step_order' => 1, 'step_key' => 'pay_finance', 'actor_type' => 'role', 'actor_value' => UserRole::Finance->value, 'notification_channels' => ['in_app'], 'is_active' => true]);
        ApprovalWorkflowStep::query()->create(['company_id' => $company->id, 'workflow_id' => $payWorkflow->id, 'step_order' => 2, 'step_key' => 'pay_owner', 'actor_type' => 'role', 'actor_value' => UserRole::Owner->value, 'notification_channels' => ['in_app', 'email'], 'min_amount' => 300000, 'is_active' => true]);

        return ['request' => $requestWorkflow, 'payment' => $payWorkflow];
    }

    /** @return array<int,Vendor> */
    private function seedVendors(Company $company): array
    {
        $profile = $this->tenantSeedProfile($company);
        $tag = Str::title($profile['tag']);
        $shift = (int) $profile['count_shift'];
        $city = (string) $profile['city'];
        $names = ['Prime Office Supplies', 'CloudCore Software', 'Metro Utilities', 'Transit Fleet Services', 'TechHub Devices', 'Bright Training Partners'];
        $rotated = array_merge(array_slice($names, $shift), array_slice($names, 0, $shift));
        $limit = 4 + $shift;
        $vendors = [];

        foreach (array_slice($rotated, 0, $limit) as $i => $name) {
            $vendorName = $tag.' '.$name;
            $vendors[] = Vendor::query()->create([
                'company_id' => $company->id,
                'name' => $vendorName,
                'vendor_type' => ['supplier', 'software', 'utility', 'service'][$i % 4],
                'contact_person' => $tag.' Contact '.($i + 1),
                'phone' => sprintf('+234820%06d', ($company->id * 100) + $i),
                'email' => sprintf('vendor%d.%s@local.test', $i + 1, Str::before((string) $company->slug, '-')),
                'address' => $city.' distribution hub',
                'bank_name' => 'Demo Bank',
                'bank_code' => '058',
                'account_name' => $vendorName,
                'account_number' => sprintf('00999%05d', ($company->id * 10) + $i),
                'is_active' => true,
            ]);
        }

        return $vendors;
    }

    /** @param array<string,Department> $departments @return array<int,DepartmentBudget> */
    private function seedBudgets(Company $company, array $departments, User $owner): array
    {
        $profile = $this->tenantSeedProfile($company);
        $multiplier = (float) $profile['multiplier'];
        $offset = (int) $profile['offset'];
        $base = (int) round(4200000 * $multiplier);
        $step = 900000 + ($offset * 80000);
        $usedRatio = 0.32 + (($offset % 4) * 0.04);
        $list = [];
        foreach (['finance', 'operations', 'procurement', 'it', 'hr'] as $i => $key) {
            $allocated = $base + ($i * $step);
            $used = (int) floor($allocated * $usedRatio);
            $list[] = DepartmentBudget::query()->create([
                'company_id' => $company->id,
                'department_id' => $departments[$key]->id,
                'period_type' => 'yearly',
                'period_start' => now()->startOfYear()->toDateString(),
                'period_end' => now()->endOfYear()->toDateString(),
                'allocated_amount' => $allocated,
                'used_amount' => $used,
                'remaining_amount' => $allocated - $used,
                'status' => 'active',
                'created_by' => $owner->id,
            ]);
        }

        return $list;
    }

    /** @param array<string,array<int,User>> $users @param array<string,Department> $departments @param array<int,Vendor> $vendors @param array<string,mixed> $workflows @return array<int,SpendRequest> */
    private function seedRequests(Company $company, array $users, array $departments, array $vendors, array $workflows): array
    {
        $profile = $this->tenantSeedProfile($company);
        $tag = Str::title($profile['tag']);
        $multiplier = (float) $profile['multiplier'];
        $offset = (int) $profile['offset'];
        $scenarios = [
            ['draft', null, 90000, 'Draft workstation upgrade', 'draft'],
            ['in_review', 1, 150000, 'Consumables restock', 'pending'],
            ['approved', null, 280000, 'Branch utility prepayment', 'approved'],
            ['rejected', null, 520000, 'Rejected marketing campaign', 'rejected'],
            ['approved_for_execution', null, 610000, 'Approved waiting dispatch', 'execution'],
            ['execution_queued', null, 300000, 'Queued payout sample', 'execution'],
            ['settled', null, 720000, 'Settled payout sample', 'execution'],
            ['failed', null, 640000, 'Failed payout sample', 'execution'],
        ];

        $requests = [];

        foreach ($scenarios as $i => $scenario) {
            $amount = (int) round(($scenario[2] + ($offset * 3000)) * $multiplier);
            $request = SpendRequest::query()->create([
                'company_id' => $company->id,
                'request_code' => sprintf('REQ-%d-%03d', $company->id, $i + 1),
                'requested_by' => $users[UserRole::Staff->value][$i % 3]->id,
                'department_id' => $departments[['operations', 'finance', 'procurement', 'it', 'hr'][$i % 5]]->id,
                'vendor_id' => $vendors[$i % count($vendors)]->id,
                'workflow_id' => $workflows['request']->id,
                'title' => $tag.' '.$scenario[3],
                'description' => 'Seeded request scenario for '.$tag,
                'amount' => $amount,
                'currency' => 'NGN',
                'status' => $scenario[0],
                'approved_amount' => in_array($scenario[0], ['approved', 'approved_for_execution', 'execution_queued', 'settled', 'failed'], true) ? $amount : null,
                'paid_amount' => $scenario[0] === 'settled' ? $amount : 0,
                'current_approval_step' => $scenario[1],
                'submitted_at' => $scenario[0] === 'draft' ? null : now()->subDays(10 - $i),
                'decided_at' => in_array($scenario[0], ['approved', 'rejected', 'approved_for_execution', 'execution_queued', 'settled', 'failed'], true) ? now()->subDays(6 - min($i, 5)) : null,
                'metadata' => ['type' => 'spend', 'request_type_code' => 'spend', 'approval_scope' => $scenario[4] === 'execution' ? 'payment_authorization' : 'request', 'seeded' => true],
                'created_by' => $users[UserRole::Staff->value][$i % 3]->id,
                'updated_by' => $users[UserRole::Staff->value][$i % 3]->id,
            ]);

            RequestItem::query()->create(['company_id' => $company->id, 'request_id' => $request->id, 'item_name' => $tag.' Item A', 'quantity' => 2, 'unit_cost' => (int) floor($amount / 3), 'line_total' => (int) floor($amount * 0.67), 'vendor_id' => $request->vendor_id, 'category' => 'operations']);
            RequestItem::query()->create(['company_id' => $company->id, 'request_id' => $request->id, 'item_name' => $tag.' Item B', 'quantity' => 1, 'unit_cost' => (int) floor($amount / 3), 'line_total' => (int) floor($amount / 3), 'vendor_id' => $request->vendor_id, 'category' => 'operations']);

            $this->seedRequestApprovalRows($request, $scenario[4], $users, $workflows);

            if ($scenario[0] !== 'draft') {
                RequestComment::query()->create(['company_id' => $company->id, 'request_id' => $request->id, 'user_id' => $users[UserRole::Staff->value][$i % 3]->id, 'body' => 'Seeded request comment']);
                RequestCommunicationLog::query()->create(['company_id' => $company->id, 'request_id' => $request->id, 'recipient_user_id' => $users[UserRole::Manager->value][0]->id, 'event' => 'request.submitted', 'channel' => 'in_app', 'status' => 'sent', 'message' => 'Seeded notification', 'sent_at' => now()->subDays(2)]);
            }

            $requests[] = $request;
        }

        return $requests;
    }

    /** @param array<string,array<int,User>> $users @param array<string,mixed> $workflows */
    private function seedRequestApprovalRows(SpendRequest $request, string $profile, array $users, array $workflows): void
    {
        $manager = $users[UserRole::Manager->value][0];
        $finance = $users[UserRole::Finance->value][0];
        $owner = $users[UserRole::Owner->value][0];
        $requestSteps = ApprovalWorkflowStep::query()->where('workflow_id', $workflows['request']->id)->orderBy('step_order')->get();
        $paySteps = ApprovalWorkflowStep::query()->where('workflow_id', $workflows['payment']->id)->orderBy('step_order')->get();

        foreach ($requestSteps as $step) {
            $status = 'queued';
            $action = null;
            $actor = null;
            if ($profile === 'pending' && (int) $step->step_order === 1) {
                $status = 'pending';
            } elseif ($profile === 'approved' || $profile === 'execution') {
                $status = 'approved';
                $action = 'approve';
                $actor = (int) $step->step_order === 1 ? $manager->id : ((int) $step->step_order === 2 ? $finance->id : $owner->id);
            } elseif ($profile === 'rejected') {
                if ((int) $step->step_order === 1) {
                    $status = 'approved';
                    $action = 'approve';
                    $actor = $manager->id;
                } elseif ((int) $step->step_order === 2) {
                    $status = 'rejected';
                    $action = 'reject';
                    $actor = $finance->id;
                }
            }

            \App\Domains\Approvals\Models\RequestApproval::query()->create([
                'company_id' => $request->company_id,
                'request_id' => $request->id,
                'scope' => 'request',
                'workflow_step_id' => $step->id,
                'step_order' => $step->step_order,
                'step_key' => $step->step_key,
                'status' => $status,
                'action' => $action,
                'acted_by' => $actor,
                'acted_at' => $actor ? now()->subDays(1) : null,
                'due_at' => now()->addHours(24),
                'reminder_count' => 0,
                'metadata' => ['seeded' => true],
            ]);
        }

        if ($profile !== 'execution') {
            return;
        }

        foreach ($paySteps as $step) {
            \App\Domains\Approvals\Models\RequestApproval::query()->create([
                'company_id' => $request->company_id,
                'request_id' => $request->id,
                'scope' => 'payment_authorization',
                'workflow_step_id' => $step->id,
                'step_order' => $step->step_order,
                'step_key' => $step->step_key,
                'status' => 'approved',
                'action' => 'approve',
                'acted_by' => (int) $step->step_order === 1 ? $finance->id : $owner->id,
                'acted_at' => now()->subHours(8),
                'due_at' => now()->subHours(2),
                'reminder_count' => 0,
                'metadata' => ['seeded' => true],
            ]);
        }
    }
    /** @param array<string,array<int,User>> $users @param array<string,Department> $departments @param array<int,Vendor> $vendors @param array<int,SpendRequest> $requests @return array<int,Expense> */
    private function seedExpenses(Company $company, array $users, array $departments, array $vendors, array $requests): array
    {
        $profile = $this->tenantSeedProfile($company);
        $tag = Str::title($profile['tag']);
        $multiplier = (float) $profile['multiplier'];
        $offset = (int) $profile['offset'];
        $list = [];
        $finance = $users[UserRole::Finance->value][0];

        for ($i = 1; $i <= 8; $i++) {
            $amount = (int) round((50000 + ($i * 5000) + ($offset * 2000)) * $multiplier);
            $list[] = Expense::query()->create([
                'company_id' => $company->id,
                'expense_code' => sprintf('EXP-%d-%03d', $company->id, $i),
                'department_id' => $departments[['operations', 'finance', 'procurement', 'it', 'hr'][$i % 5]]->id,
                'vendor_id' => $vendors[$i % count($vendors)]->id,
                'title' => $tag.' direct expense '.$i,
                'description' => 'Seeded direct expense for '.$tag,
                'amount' => $amount,
                'expense_date' => now()->subDays(10 - $i)->toDateString(),
                'payment_method' => ['bank_transfer', 'card', 'cash'][$i % 3],
                'paid_by_user_id' => $users[UserRole::Staff->value][$i % 3]->id,
                'created_by' => $finance->id,
                'status' => $i === 8 ? 'voided' : 'posted',
                'voided_by' => $i === 8 ? $finance->id : null,
                'voided_at' => $i === 8 ? now()->subDay() : null,
                'void_reason' => $i === 8 ? 'seeded_void' : null,
                'is_direct' => true,
            ]);
        }

        foreach (collect($requests)->whereIn('status', ['approved', 'settled'])->take(2) as $index => $request) {
            $list[] = Expense::query()->create([
                'company_id' => $company->id,
                'expense_code' => sprintf('EXP-%d-RQ%02d', $company->id, $index + 1),
                'request_id' => $request->id,
                'department_id' => $request->department_id,
                'vendor_id' => $request->vendor_id,
                'title' => $tag.' request expense '.$request->request_code,
                'description' => 'Seeded request-linked expense for '.$tag,
                'amount' => (int) floor(((int) $request->amount) * 0.9),
                'expense_date' => now()->subDays(2 + $index)->toDateString(),
                'payment_method' => 'bank_transfer',
                'paid_by_user_id' => $finance->id,
                'created_by' => $finance->id,
                'status' => 'posted',
                'is_direct' => false,
            ]);
        }

        return $list;
    }

    /** @return array<string,array<int,mixed>> */
    private function seedVendorFinance(Company $company, User $finance, array $vendors): array
    {
        $profile = $this->tenantSeedProfile($company);
        $multiplier = (float) $profile['multiplier'];
        $invoices = [];
        $payments = [];

        foreach ($vendors as $i => $vendor) {
            $total = (int) round((120000 + ($i * 30000)) * $multiplier);
            $status = ['unpaid', 'part_paid', 'paid', 'unpaid', 'part_paid', 'unpaid'][$i % 6];
            $paid = $status === 'paid' ? $total : ($status === 'part_paid' ? (int) floor($total * 0.45) : 0);
            $invoice = VendorInvoice::query()->create([
                'company_id' => $company->id,
                'vendor_id' => $vendor->id,
                'invoice_number' => sprintf('INV-%d-%03d', $company->id, $i + 1),
                'invoice_date' => now()->subDays(30 - $i)->toDateString(),
                'due_date' => now()->subDays(8 - $i)->toDateString(),
                'currency' => 'NGN',
                'total_amount' => $total,
                'paid_amount' => $paid,
                'outstanding_amount' => $total - $paid,
                'status' => $status,
                'description' => 'Seeded invoice',
                'created_by' => $finance->id,
                'updated_by' => $finance->id,
            ]);
            $invoices[] = $invoice;

            if ($paid > 0) {
                $payments[] = VendorInvoicePayment::query()->create([
                    'company_id' => $company->id,
                    'vendor_id' => $vendor->id,
                    'vendor_invoice_id' => $invoice->id,
                    'payment_reference' => sprintf('VPAY-%d-%03d', $company->id, $i + 1),
                    'amount' => $paid,
                    'payment_date' => now()->subDays(3)->toDateString(),
                    'payment_method' => 'bank_transfer',
                    'created_by' => $finance->id,
                    'updated_by' => $finance->id,
                ]);
            }

            VendorCommunicationLog::query()->create([
                'company_id' => $company->id,
                'vendor_id' => $vendor->id,
                'vendor_invoice_id' => $invoice->id,
                'recipient_user_id' => $finance->id,
                'event' => 'vendor.invoice.reminder',
                'channel' => 'email',
                'status' => $i % 3 === 0 ? 'queued' : 'sent',
                'recipient_email' => $vendor->email,
                'recipient_phone' => $vendor->phone,
                'reminder_date' => now()->toDateString(),
                'dedupe_key' => sprintf('vendor-%d-%d', $company->id, $invoice->id),
                'message' => 'Seeded payable reminder',
                'sent_at' => $i % 3 === 0 ? null : now()->subDay(),
            ]);
        }

        return ['invoices' => $invoices, 'payments' => $payments];
    }

    /** @param array<string,array<int,User>> $users @param array<int,SpendRequest> $requests @param array<int,DepartmentBudget> $budgets @param array<string,array<int,mixed>> $vendorFinance @param array<int,Expense> $expenses */
    private function seedProcurementAndTreasury(Company $company, array $users, array $requests, array $budgets, array $vendorFinance, array $expenses): void
    {
        $profile = $this->tenantSeedProfile($company);
        $multiplier = (float) $profile['multiplier'];
        $offset = (int) $profile['offset'];
        $finance = $users[UserRole::Finance->value][0];
        $manager = $users[UserRole::Manager->value][1];
        $request = collect($requests)->firstWhere('status', 'approved') ?? $requests[0];
        $invoice = $vendorFinance['invoices'][0];
        $poSubtotal = (int) round(420000 * $multiplier);
        $poTax = (int) round($poSubtotal * 0.075);
        $poTotal = $poSubtotal + $poTax;

        $po = PurchaseOrder::query()->create([
            'company_id' => $company->id,
            'spend_request_id' => $request->id,
            'department_budget_id' => $budgets[0]->id,
            'vendor_id' => $request->vendor_id,
            'po_number' => sprintf('PO-%d-001', $company->id),
            'po_status' => PurchaseOrder::STATUS_RECEIVED,
            'currency_code' => 'NGN',
            'subtotal_amount' => $poSubtotal,
            'tax_amount' => $poTax,
            'total_amount' => $poTotal,
            'issued_at' => now()->subDays(8),
            'expected_delivery_at' => now()->addDays(5)->toDateString(),
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        $unitPrice = (int) round(120000 * $multiplier);
        $poi = PurchaseOrderItem::query()->create([
            'company_id' => $company->id,
            'purchase_order_id' => $po->id,
            'line_number' => 1,
            'item_description' => 'Seeded PO item',
            'quantity' => 3,
            'unit_price' => $unitPrice,
            'line_total' => $unitPrice * 3,
            'currency_code' => 'NGN',
            'received_quantity' => 3,
            'received_total' => $unitPrice * 3,
        ]);

        $gr = GoodsReceipt::query()->create([
            'company_id' => $company->id,
            'purchase_order_id' => $po->id,
            'receipt_number' => sprintf('GR-%d-001', $company->id),
            'received_at' => now()->subDays(4),
            'received_by_user_id' => $manager->id,
            'receipt_status' => GoodsReceipt::STATUS_CONFIRMED,
            'created_by' => $manager->id,
            'updated_by' => $manager->id,
        ]);

        GoodsReceiptItem::query()->create([
            'company_id' => $company->id,
            'goods_receipt_id' => $gr->id,
            'purchase_order_item_id' => $poi->id,
            'received_quantity' => 3,
            'received_unit_cost' => 120000,
            'received_total' => 360000,
        ]);

        ProcurementCommitment::query()->create([
            'company_id' => $company->id,
            'purchase_order_id' => $po->id,
            'department_budget_id' => $budgets[0]->id,
            'commitment_status' => ProcurementCommitment::STATUS_ACTIVE,
            'amount' => $poTotal,
            'currency_code' => 'NGN',
            'effective_at' => now()->subDays(7),
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        $invoice->forceFill(['purchase_order_id' => $po->id, 'updated_by' => $finance->id])->save();
        $match = InvoiceMatchResult::query()->create([
            'company_id' => $company->id,
            'purchase_order_id' => $po->id,
            'vendor_invoice_id' => $invoice->id,
            'match_status' => InvoiceMatchResult::STATUS_MISMATCH,
            'match_score' => 71.5,
            'mismatch_reason' => 'quantity_mismatch',
            'matched_at' => now()->subDays(2),
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        InvoiceMatchException::query()->create([
            'company_id' => $company->id,
            'invoice_match_result_id' => $match->id,
            'purchase_order_id' => $po->id,
            'vendor_invoice_id' => $invoice->id,
            'exception_code' => 'quantity_mismatch',
            'exception_status' => InvoiceMatchException::STATUS_OPEN,
            'severity' => InvoiceMatchException::SEVERITY_HIGH,
            'details' => 'Seeded procurement exception',
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        $account = BankAccount::query()->create([
            'company_id' => $company->id,
            'account_name' => $company->name.' Main Account',
            'bank_name' => 'Flowdesk Seed Bank',
            'account_number_masked' => '***'.str_pad((string) $company->id, 4, '0', STR_PAD_LEFT),
            'account_reference' => 'ACC-'.$company->id,
            'currency_code' => 'NGN',
            'is_primary' => true,
            'is_active' => true,
            'last_statement_at' => now()->subHours(3),
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        $statement = BankStatement::query()->create([
            'company_id' => $company->id,
            'bank_account_id' => $account->id,
            'statement_reference' => 'STM-'.$company->id,
            'statement_date' => now()->toDateString(),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'opening_balance' => (int) round(12000000 * $multiplier),
            'closing_balance' => (int) round(11300000 * $multiplier),
            'import_status' => BankStatement::STATUS_IMPORTED,
            'imported_at' => now()->subHours(2),
            'imported_by_user_id' => $finance->id,
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        $line = BankStatementLine::query()->create([
            'company_id' => $company->id,
            'bank_statement_id' => $statement->id,
            'bank_account_id' => $account->id,
            'line_reference' => 'LINE-'.$company->id.'-1',
            'posted_at' => now()->subHours(5),
            'value_date' => now()->toDateString(),
            'description' => 'Seeded statement line',
            'direction' => 'debit',
            'amount' => (int) round((320000 + ($offset * 5000)) * $multiplier),
            'currency_code' => 'NGN',
            'balance_after' => (int) round(11680000 * $multiplier),
            'source_hash' => hash('sha256', 'line-'.$company->id),
            'is_reconciled' => true,
            'reconciled_at' => now()->subHours(1),
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        $run = PaymentRun::query()->create([
            'company_id' => $company->id,
            'run_code' => 'PAYRUN-'.$company->id,
            'run_status' => PaymentRun::STATUS_COMPLETED,
            'run_type' => 'mixed',
            'scheduled_at' => now()->subHours(4),
            'processed_at' => now()->subHours(3),
            'total_items' => 2,
            'total_amount' => (int) round(440000 * $multiplier),
            'currency_code' => 'NGN',
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        PaymentRunItem::query()->create([
            'company_id' => $company->id,
            'payment_run_id' => $run->id,
            'expense_id' => $expenses[0]->id,
            'item_reference' => 'RUNITEM-'.$company->id,
            'item_status' => PaymentRunItem::STATUS_SKIPPED,
            'amount' => (int) round(120000 * $multiplier),
            'currency_code' => 'NGN',
            'processed_at' => now()->subHours(3),
            'failure_reason' => 'Seeded skipped flow',
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        $matchRow = ReconciliationMatch::query()->create([
            'company_id' => $company->id,
            'bank_statement_line_id' => $line->id,
            'match_target_type' => Expense::class,
            'match_target_id' => $expenses[0]->id,
            'match_stream' => ReconciliationMatch::STREAM_EXPENSE_EVIDENCE,
            'match_status' => ReconciliationMatch::STATUS_CONFLICT,
            'confidence_score' => 68.5,
            'matched_by' => 'system',
            'matched_at' => now()->subHours(1),
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);

        ReconciliationException::query()->create([
            'company_id' => $company->id,
            'bank_statement_line_id' => $line->id,
            'reconciliation_match_id' => $matchRow->id,
            'exception_code' => 'expense_evidence_missing',
            'exception_status' => ReconciliationException::STATUS_OPEN,
            'severity' => ReconciliationException::SEVERITY_HIGH,
            'match_stream' => ReconciliationException::STREAM_EXPENSE_EVIDENCE,
            'next_action' => 'Attach receipt',
            'details' => 'Seeded treasury exception',
            'created_by' => $finance->id,
            'updated_by' => $finance->id,
        ]);
    }
    /** @param array<string,array<int,User>> $users @param array<string,Department> $departments */
    private function seedAssets(Company $company, array $users, array $departments): void
    {
        $profile = $this->tenantSeedProfile($company);
        $tag = Str::title($profile['tag']);
        $multiplier = (float) $profile['multiplier'];
        $finance = $users[UserRole::Finance->value][0];
        $categories = [];

        foreach (['Computers', 'Furniture', 'Vehicles', 'Network'] as $i => $name) {
            $categories[] = AssetCategory::query()->create([
                'company_id' => $company->id,
                'name' => $name,
                'code' => strtoupper(Str::substr($name, 0, 3)).$i,
                'is_active' => true,
                'created_by' => $finance->id,
                'updated_by' => $finance->id,
            ]);
        }

        for ($i = 1; $i <= 8; $i++) {
            $status = $i <= 3 ? Asset::STATUS_ACTIVE : ($i <= 5 ? Asset::STATUS_ASSIGNED : ($i <= 7 ? Asset::STATUS_IN_MAINTENANCE : Asset::STATUS_DISPOSED));
            $asset = Asset::query()->create([
                'company_id' => $company->id,
                'asset_category_id' => $categories[$i % count($categories)]->id,
                'asset_code' => sprintf('AST-%d-%03d', $company->id, $i),
                'name' => $tag.' Asset '.$i,
                'serial_number' => sprintf('SER-%d-%03d', $company->id, $i),
                'acquisition_date' => now()->subMonths(8 + $i)->toDateString(),
                'purchase_amount' => (int) round((150000 + ($i * 25000)) * $multiplier),
                'currency' => 'NGN',
                'status' => $status,
                'condition' => $status === Asset::STATUS_DISPOSED ? 'retired' : 'good',
                'assigned_to_user_id' => $status === Asset::STATUS_ASSIGNED ? $users[UserRole::Staff->value][$i % 3]->id : null,
                'assigned_department_id' => $status === Asset::STATUS_ASSIGNED ? $departments['operations']->id : null,
                'assigned_at' => $status === Asset::STATUS_ASSIGNED ? now()->subDays(10) : null,
                'disposed_at' => $status === Asset::STATUS_DISPOSED ? now()->subDays(2) : null,
                'disposal_reason' => $status === Asset::STATUS_DISPOSED ? 'seeded_disposal' : null,
                'salvage_amount' => $status === Asset::STATUS_DISPOSED ? 20000 : null,
                'last_maintenance_at' => now()->subDays(20)->toDateString(),
                'maintenance_due_date' => now()->addDays(10)->toDateString(),
                'warranty_expires_at' => now()->addMonths(4)->toDateString(),
                'created_by' => $finance->id,
                'updated_by' => $finance->id,
            ]);

            AssetEvent::query()->create([
                'company_id' => $company->id,
                'asset_id' => $asset->id,
                'event_type' => AssetEvent::TYPE_CREATED,
                'event_date' => now()->subMonths(3),
                'actor_user_id' => $finance->id,
                'amount' => $asset->purchase_amount,
                'currency' => 'NGN',
                'summary' => 'Seeded asset created',
                'details' => 'Seeded event',
                'metadata' => ['seeded' => true],
            ]);

            AssetCommunicationLog::query()->create([
                'company_id' => $company->id,
                'asset_id' => $asset->id,
                'recipient_user_id' => $users[UserRole::Manager->value][0]->id,
                'event' => 'asset.maintenance.reminder',
                'channel' => 'in_app',
                'status' => $i % 2 === 0 ? 'sent' : 'queued',
                'recipient_email' => $users[UserRole::Manager->value][0]->email,
                'recipient_phone' => $users[UserRole::Manager->value][0]->phone,
                'reminder_date' => now()->addDays(5)->toDateString(),
                'dedupe_key' => sprintf('asset-%d-%d', $company->id, $asset->id),
                'message' => 'Seeded asset reminder',
                'sent_at' => $i % 2 === 0 ? now()->subHours(4) : null,
            ]);
        }
    }

    /** @param array<string,array<int,User>> $users @param array<int,SpendRequest> $requests */
    private function seedExecution(Company $company, array $users, array $requests): void
    {
        $profile = $this->tenantSeedProfile($company);
        $multiplier = (float) $profile['multiplier'];
        $finance = $users[UserRole::Finance->value][0];
        $subscription = TenantSubscription::query()->where('company_id', $company->id)->first();
        if (! $subscription) {
            return;
        }

        foreach (['settled', 'failed', 'queued'] as $i => $status) {
            $attempt = TenantSubscriptionBillingAttempt::query()->create([
                'company_id' => $company->id,
                'tenant_subscription_id' => $subscription->id,
                'provider_key' => (string) ($subscription->execution_provider ?: 'seeded_provider'),
                'billing_cycle_key' => now()->subMonths($i)->format('Y-m'),
                'idempotency_key' => sprintf('billing-%d-%d', $company->id, $i),
                'attempt_status' => $status,
                'amount' => (int) round((120000 + ($i * 10000)) * $multiplier),
                'currency_code' => 'NGN',
                'period_start' => now()->subMonths($i)->startOfMonth()->toDateString(),
                'period_end' => now()->subMonths($i)->endOfMonth()->toDateString(),
                'provider_reference' => 'BILLREF-'.strtoupper(Str::random(6)),
                'attempt_count' => $status === 'failed' ? 2 : 1,
                'queued_at' => now()->subDays(3),
                'processed_at' => $status === 'queued' ? null : now()->subDays(2),
                'settled_at' => $status === 'settled' ? now()->subDay() : null,
                'failed_at' => $status === 'failed' ? now()->subDay() : null,
                'next_retry_at' => $status === 'failed' ? now()->addMinutes(30) : null,
                'error_code' => $status === 'failed' ? 'provider_timeout' : null,
                'error_message' => $status === 'failed' ? 'seeded_failure' : null,
                'provider_response' => ['seeded' => true],
                'metadata' => ['seeded' => true],
                'created_by' => $finance->id,
                'updated_by' => $finance->id,
            ]);

            ExecutionWebhookEvent::query()->create([
                'provider_key' => (string) ($subscription->execution_provider ?: 'seeded_provider'),
                'external_event_id' => 'WEBHOOK-BILL-'.strtoupper(Str::random(6)),
                'company_id' => $company->id,
                'tenant_subscription_id' => $subscription->id,
                'tenant_subscription_billing_attempt_id' => $attempt->id,
                'event_type' => 'billing.'.$status,
                'verification_status' => 'valid',
                'processing_status' => $status === 'queued' ? 'queued' : 'processed',
                'received_at' => now()->subHours(6),
                'payload' => ['seeded' => true],
                'normalized_payload' => ['seeded' => true],
                'processed_at' => $status === 'queued' ? null : now()->subHours(5),
            ]);
        }

        foreach (collect($requests)->whereIn('status', ['approved_for_execution', 'execution_queued', 'settled', 'failed']) as $request) {
            $status = match ((string) $request->status) {
                'approved_for_execution', 'execution_queued' => 'queued',
                'settled' => 'settled',
                'failed' => 'failed',
                default => 'queued',
            };

            $attempt = RequestPayoutExecutionAttempt::query()->create([
                'company_id' => $company->id,
                'request_id' => $request->id,
                'tenant_subscription_id' => $subscription->id,
                'provider_key' => (string) ($subscription->execution_provider ?: 'seeded_provider'),
                'execution_channel' => 'bank_transfer',
                'idempotency_key' => sprintf('payout-%d-%d', $company->id, $request->id),
                'execution_status' => $status,
                'amount' => (float) ($request->approved_amount ?: $request->amount),
                'currency_code' => 'NGN',
                'provider_reference' => 'PAYOUT-'.strtoupper(Str::random(6)),
                'attempt_count' => $status === 'failed' ? 2 : 1,
                'queued_at' => now()->subHours(20),
                'processed_at' => in_array($status, ['settled', 'failed'], true) ? now()->subHours(18) : null,
                'settled_at' => $status === 'settled' ? now()->subHours(17) : null,
                'failed_at' => $status === 'failed' ? now()->subHours(17) : null,
                'next_retry_at' => $status === 'failed' ? now()->addMinutes(20) : null,
                'error_code' => $status === 'failed' ? 'destination_rejected' : null,
                'error_message' => $status === 'failed' ? 'seeded_failure' : null,
                'provider_response' => ['seeded' => true],
                'metadata' => ['seeded' => true],
                'created_by' => $finance->id,
                'updated_by' => $finance->id,
            ]);

            ExecutionWebhookEvent::query()->create([
                'provider_key' => (string) ($subscription->execution_provider ?: 'seeded_provider'),
                'external_event_id' => 'WEBHOOK-PAY-'.strtoupper(Str::random(6)),
                'company_id' => $company->id,
                'tenant_subscription_id' => $subscription->id,
                'request_payout_execution_attempt_id' => $attempt->id,
                'event_type' => 'payout.'.$status,
                'verification_status' => 'valid',
                'processing_status' => $status === 'queued' ? 'queued' : 'processed',
                'received_at' => now()->subHours(4),
                'payload' => ['seeded' => true],
                'normalized_payload' => ['seeded' => true],
                'processed_at' => $status === 'queued' ? null : now()->subHours(3),
            ]);
        }
    }

    /** @param array<string,array<int,User>> $users @param array<string,mixed> $tenant @param array<int,SpendRequest> $requests @param array<int,Expense> $expenses @param array<int,Vendor> $vendors */
    private function seedAuditAndPilot(Company $company, array $users, array $tenant, array $requests, array $expenses, array $vendors): void
    {
        $owner = $users[UserRole::Owner->value][0];
        $finance = $users[UserRole::Finance->value][0];

        foreach ([
            ['tenant.subscription.updated', TenantSubscription::class, TenantSubscription::query()->where('company_id', $company->id)->value('id'), 'subscription seeded'],
            ['request.approved', SpendRequest::class, $requests[2]->id ?? null, 'request approved seeded'],
            ['execution.ops.auto_recovery.summary', 'execution_recovery', null, 'auto recovery summary seeded'],
            ['treasury.exception.opened', ReconciliationException::class, null, 'treasury exception seeded'],
            ['tenant.rollout.pilot_kpi_capture.recorded', TenantPilotKpiCapture::class, null, 'pilot kpi seeded'],
        ] as $i => $event) {
            TenantAuditEvent::query()->create([
                'company_id' => $company->id,
                'actor_user_id' => $i % 2 === 0 ? $owner->id : $finance->id,
                'action' => $event[0],
                'entity_type' => $event[1],
                'entity_id' => $event[2],
                'description' => $event[3],
                'metadata' => ['seeded' => true],
                'event_at' => now()->subDays(5 - min($i, 4)),
            ]);
        }

        ActivityLog::query()->create(['company_id' => $company->id, 'user_id' => $owner->id, 'action' => 'request.created', 'entity_type' => SpendRequest::class, 'entity_id' => $requests[0]->id ?? null, 'metadata' => ['seeded' => true], 'created_at' => now()->subDays(2)]);
        ActivityLog::query()->create(['company_id' => $company->id, 'user_id' => $finance->id, 'action' => 'expense.posted', 'entity_type' => Expense::class, 'entity_id' => $expenses[0]->id ?? null, 'metadata' => ['seeded' => true], 'created_at' => now()->subDays(2)]);
        ActivityLog::query()->create(['company_id' => $company->id, 'user_id' => $finance->id, 'action' => 'vendor.invoice.recorded', 'entity_type' => Vendor::class, 'entity_id' => $vendors[0]->id ?? null, 'metadata' => ['seeded' => true], 'created_at' => now()->subDays(2)]);

        $subscription = TenantSubscription::query()->where('company_id', $company->id)->first();
        $activeUsers = User::query()->where('company_id', $company->id)->where('is_active', true)->count();
        $seatLimit = (int) ($subscription?->seat_limit ?: 0);

        TenantUsageCounter::query()->create([
            'company_id' => $company->id,
            'snapshot_at' => now(),
            'active_users' => $activeUsers,
            'seat_limit' => $subscription?->seat_limit,
            'seat_utilization_percent' => $seatLimit > 0 ? round(($activeUsers / $seatLimit) * 100, 2) : null,
            'requests_count' => count($requests),
            'expenses_count' => count($expenses),
            'vendors_count' => count($vendors),
            'assets_count' => Asset::query()->where('company_id', $company->id)->count(),
            'warning_level' => 'normal',
            'captured_by' => $owner->id,
        ]);

        TenantPilotKpiCapture::query()->create([
            'company_id' => $company->id,
            'window_label' => 'baseline',
            'window_start' => now()->subDays(45),
            'window_end' => now()->subDays(30),
            'match_pass_rate_percent' => 81.4,
            'open_procurement_exceptions' => 4,
            'procurement_exception_avg_open_hours' => 50.2,
            'auto_reconciliation_rate_percent' => 68.1,
            'open_treasury_exceptions' => 5,
            'treasury_exception_avg_open_hours' => 44.3,
            'blocked_payout_count' => 3,
            'manual_override_count' => 5,
            'incident_count' => 4,
            'incident_rate_per_week' => 1.4,
            'notes' => 'seeded baseline',
            'captured_at' => now()->subDays(30),
            'captured_by_user_id' => $finance->id,
        ]);

        TenantPilotKpiCapture::query()->create([
            'company_id' => $company->id,
            'window_label' => 'pilot',
            'window_start' => now()->subDays(14),
            'window_end' => now()->subDay(),
            'match_pass_rate_percent' => 93.8,
            'open_procurement_exceptions' => 2,
            'procurement_exception_avg_open_hours' => 17.9,
            'auto_reconciliation_rate_percent' => 89.3,
            'open_treasury_exceptions' => 2,
            'treasury_exception_avg_open_hours' => 13.8,
            'blocked_payout_count' => 1,
            'manual_override_count' => 2,
            'incident_count' => 2,
            'incident_rate_per_week' => 0.7,
            'notes' => 'seeded pilot',
            'captured_at' => now()->subDay(),
            'captured_by_user_id' => $finance->id,
        ]);

        TenantPilotWaveOutcome::query()->create([
            'company_id' => $company->id,
            'wave_label' => 'wave-1',
            'outcome' => (string) $tenant['outcome'],
            'decision_at' => now()->subDay(),
            'notes' => 'seeded wave decision',
            'metadata' => ['seeded' => true],
            'decided_by_user_id' => $owner->id,
        ]);
    }
}


