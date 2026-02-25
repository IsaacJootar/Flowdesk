<?php

namespace App\Domains\Vendors\Models;

use App\Domains\Company\Models\Company;
use App\Enums\UserRole;
use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyVendorPolicySetting extends Model
{
    use CompanyScoped;
    use HasFactory;

    public const ACTION_VIEW_DIRECTORY = 'view_vendor_directory';
    public const ACTION_CREATE_VENDOR = 'create_vendor';
    public const ACTION_UPDATE_VENDOR = 'update_vendor';
    public const ACTION_DELETE_VENDOR = 'delete_vendor';
    public const ACTION_MANAGE_INVOICES = 'manage_vendor_invoices';
    public const ACTION_RECORD_PAYMENTS = 'record_vendor_payments';
    public const ACTION_EXPORT_STATEMENTS = 'export_vendor_statements';
    public const ACTION_MANAGE_COMMUNICATIONS = 'manage_vendor_communications';

    /**
     * @var array<int, string>
     */
    public const ACTIONS = [
        self::ACTION_VIEW_DIRECTORY,
        self::ACTION_CREATE_VENDOR,
        self::ACTION_UPDATE_VENDOR,
        self::ACTION_DELETE_VENDOR,
        self::ACTION_MANAGE_INVOICES,
        self::ACTION_RECORD_PAYMENTS,
        self::ACTION_EXPORT_STATEMENTS,
        self::ACTION_MANAGE_COMMUNICATIONS,
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
            self::ACTION_VIEW_DIRECTORY => self::actionDefaults([
                UserRole::Owner->value,
                UserRole::Finance->value,
                UserRole::Auditor->value,
            ]),
            self::ACTION_CREATE_VENDOR => self::actionDefaults([
                UserRole::Owner->value,
                UserRole::Finance->value,
            ]),
            self::ACTION_UPDATE_VENDOR => self::actionDefaults([
                UserRole::Owner->value,
                UserRole::Finance->value,
            ]),
            self::ACTION_DELETE_VENDOR => self::actionDefaults([
                UserRole::Owner->value,
                UserRole::Finance->value,
            ]),
            self::ACTION_MANAGE_INVOICES => self::actionDefaults([
                UserRole::Owner->value,
                UserRole::Finance->value,
            ]),
            self::ACTION_RECORD_PAYMENTS => self::actionDefaults([
                UserRole::Owner->value,
                UserRole::Finance->value,
            ]),
            self::ACTION_EXPORT_STATEMENTS => self::actionDefaults([
                UserRole::Owner->value,
                UserRole::Finance->value,
                UserRole::Auditor->value,
            ]),
            self::ACTION_MANAGE_COMMUNICATIONS => self::actionDefaults([
                UserRole::Owner->value,
                UserRole::Finance->value,
            ]),
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function policyForAction(string $action): array
    {
        $defaults = self::defaultActionPolicies();
        $fallback = $defaults[$action] ?? self::actionDefaults([
            UserRole::Owner->value,
            UserRole::Finance->value,
        ]);
        $configured = (array) (($this->action_policies ?? [])[$action] ?? []);

        $allowedRoles = array_values(array_unique(array_values(array_filter(
            (array) ($configured['allowed_roles'] ?? $fallback['allowed_roles']),
            fn ($role): bool => in_array((string) $role, UserRole::values(), true)
        ))));

        if ($allowedRoles === []) {
            $allowedRoles = $fallback['allowed_roles'];
        }

        return [
            'allowed_roles' => $allowedRoles,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

