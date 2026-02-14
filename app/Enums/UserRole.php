<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Finance = 'finance';
    case Manager = 'manager';
    case Staff = 'staff';
    case Auditor = 'auditor';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
