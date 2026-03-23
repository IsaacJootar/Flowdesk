<?php

namespace App\Support;

class Money
{
    /**
     * Flowdesk stores and exchanges monetary values in major currency units.
     *
     * Integer business columns therefore represent whole major units already
     * entered by users/imports, while provider-facing decimal columns stay in
     * major-unit decimals. We never apply implicit x100 conversions.
     */
    public static function formatCurrency(int|float|string|null $amount, ?string $currencyCode = 'NGN', int $decimals = 2): string
    {
        return trim(sprintf(
            '%s %s',
            strtoupper(trim((string) ($currencyCode ?: 'NGN'))),
            self::formatPlain($amount, $decimals)
        ));
    }

    public static function formatPlain(int|float|string|null $amount, int $decimals = 2): string
    {
        return number_format(self::normalize($amount), $decimals, '.', ',');
    }

    public static function formatCount(int|float|string|null $value): string
    {
        return number_format((int) round(self::normalize($value)));
    }

    public static function parseMajor(int|float|string|null $amount): float
    {
        if ($amount === null) {
            return 0.0;
        }

        if (is_int($amount) || is_float($amount)) {
            return (float) $amount;
        }

        $normalized = preg_replace('/[^\d\.\-]/', '', trim($amount));

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private static function normalize(int|float|string|null $amount): float
    {
        return self::parseMajor($amount);
    }
}
