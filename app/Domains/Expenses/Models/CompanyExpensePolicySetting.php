<?php

namespace App\Domains\Expenses\Models;

use App\Domains\Company\Models\Company;
use App\Enums\UserRole;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyExpensePolicySetting extends Model
{
    use CompanyScoped;
    use HasFactory;

    public const ACTION_CREATE_DIRECT = 'create_direct_expense';
    public const ACTION_CREATE_FROM_REQUEST = 'create_expense_from_request';
    public const ACTION_EDIT_POSTED = 'edit_posted_expense';
    public const ACTION_VOID = 'void_expense';

    /**
     * @var array<int, string>
     */
    public const ACTIONS = [
        self::ACTION_CREATE_DIRECT,
        self::ACTION_CREATE_FROM_REQUEST,
        self::ACTION_EDIT_POSTED,
        self::ACTION_VOID,
    ];

    protected $fillable = [
        'company_id',
        'action_policies',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'action_policies' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultAttributes(): array
    {
        return [
            'action_policies' => self::defaultActionPolicies(),
            'metadata' => null,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function defaultActionPolicies(): array
    {
        return [
            self::ACTION_CREATE_DIRECT => self::actionDefaults([UserRole::Owner->value, UserRole::Finance->value]),
            self::ACTION_CREATE_FROM_REQUEST => self::actionDefaults([UserRole::Owner->value, UserRole::Finance->value]),
            self::ACTION_EDIT_POSTED => self::actionDefaults([UserRole::Owner->value, UserRole::Finance->value]),
            self::ACTION_VOID => self::actionDefaults([UserRole::Owner->value, UserRole::Finance->value]),
        ];
    }

    /**
     * @param  array<int, string>  $allowedRoles
     * @return array<string, mixed>
     */
    private static function actionDefaults(array $allowedRoles): array
    {
        return [
            'allowed_roles' => $allowedRoles,
            'department_ids' => [],
            'amount_limits' => [],
            'require_secondary_approval_over_limit' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function policyForAction(string $action): array
    {
        $defaults = self::defaultActionPolicies();
        $fallback = $defaults[$action] ?? self::actionDefaults([UserRole::Owner->value, UserRole::Finance->value]);
        $configured = (array) (($this->action_policies ?? [])[$action] ?? []);

        $allowedRoles = array_values(array_unique(array_values(array_filter(
            (array) ($configured['allowed_roles'] ?? $fallback['allowed_roles']),
            fn ($role): bool => in_array((string) $role, UserRole::values(), true)
        ))));

        if ($allowedRoles === []) {
            $allowedRoles = $fallback['allowed_roles'];
        }

        $departmentIds = array_values(array_unique(array_values(array_filter(
            array_map('intval', (array) ($configured['department_ids'] ?? [])),
            fn (int $id): bool => $id > 0
        ))));

        $amountLimits = [];
        foreach ((array) ($configured['amount_limits'] ?? []) as $role => $limit) {
            $role = (string) $role;
            if (! in_array($role, UserRole::values(), true)) {
                continue;
            }

            $limitValue = is_numeric($limit) ? (int) $limit : null;
            if ($limitValue !== null && $limitValue > 0) {
                $amountLimits[$role] = $limitValue;
            }
        }

        return [
            'allowed_roles' => $allowedRoles,
            'department_ids' => $departmentIds,
            'amount_limits' => $amountLimits,
            'require_secondary_approval_over_limit' => (bool) ($configured['require_secondary_approval_over_limit'] ?? false),
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

