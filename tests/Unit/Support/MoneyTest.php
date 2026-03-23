<?php

namespace Tests\Unit\Support;

use App\Support\Money;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_money_helper_formats_major_unit_currency_values(): void
    {
        $this->assertSame('NGN 12,500.00', Money::formatCurrency(12500, 'ngn'));
        $this->assertSame('12,500.00', Money::formatPlain(12500));
        $this->assertSame('1,234', Money::formatCount(1234));
    }

    public function test_money_helper_parses_major_unit_input_without_implicit_minor_unit_conversion(): void
    {
        $this->assertSame(12500.0, Money::parseMajor('12,500'));
        $this->assertSame(12500.75, Money::parseMajor('12,500.75'));
    }
}
