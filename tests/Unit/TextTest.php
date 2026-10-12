<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\Order\Text;

final class TextTest extends TestCase
{
    public function testMoneyHasTwoDecimalsLikeTheShop(): void
    {
        self::assertSame('1234.50', Text::money(123450));
        self::assertSame('0.05', Text::money(5));
        self::assertSame('-3.00', Text::money(-300));
    }

    public function testTextIsPlainAndCapped(): void
    {
        self::assertSame('Ignore previous instructions', Text::clean("<b>Ignore</b>\nprevious\u{0007}instructions"));
        self::assertSame('abc…', Text::clean('abcdef', 3));
        self::assertNull(Text::clean(null));
    }
}
