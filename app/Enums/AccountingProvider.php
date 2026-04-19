<?php

namespace App\Enums;

enum AccountingProvider: string
{
    case Csv = 'csv';
    case QuickBooks = 'quickbooks';
    case Sage = 'sage';
    case Xero = 'xero';

    public function label(): string
    {
        return match ($this) {
            self::Csv => 'CSV export',
            self::QuickBooks => 'QuickBooks',
            self::Sage => 'Sage',
            self::Xero => 'Xero',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $provider): string => $provider->value,
            self::cases()
        );
    }

    public static function normalize(mixed $value): string
    {
        $provider = strtolower(trim((string) $value));

        return in_array($provider, self::values(), true) ? $provider : self::Csv->value;
    }
}
