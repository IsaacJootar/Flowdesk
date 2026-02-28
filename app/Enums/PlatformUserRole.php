<?php

namespace App\Enums;

enum PlatformUserRole: string
{
    case PlatformOwner = 'platform_owner';
    case PlatformBillingAdmin = 'platform_billing_admin';
    case PlatformOpsAdmin = 'platform_ops_admin';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::PlatformOwner => 'Platform Owner',
            self::PlatformBillingAdmin => 'Platform Billing Admin',
            self::PlatformOpsAdmin => 'Platform Operations Admin',
        };
    }
}

