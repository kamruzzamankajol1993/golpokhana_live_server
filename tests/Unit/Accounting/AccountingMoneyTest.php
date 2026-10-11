<?php

namespace Tests\Unit\Accounting;

use App\Services\Accounting\AccountingMoney;
use PHPUnit\Framework\TestCase;

final class AccountingMoneyTest extends TestCase
{
    public function test_fixed_precision_conversions(): void
    {
        self::assertSame(123456, AccountingMoney::units('12.3456'));
        self::assertSame(1, AccountingMoney::units('0.0001'));
        self::assertSame(0, AccountingMoney::units('0'));
        self::assertSame('12.3456', AccountingMoney::format(123456));
    }

    public function test_signed_reporting_format(): void
    {
        self::assertSame('-0.5000', AccountingMoney::format(-5000));
        self::assertSame('-1.0500', AccountingMoney::format(-10500));
        self::assertSame('0.0000', AccountingMoney::format(0));
    }
}
