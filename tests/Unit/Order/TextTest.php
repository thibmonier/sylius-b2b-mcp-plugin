<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Order;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\Order\Text;

final class TextTest extends TestCase
{
    public function testCleanNull(): void
    {
        self::assertNull(Text::clean(null));
    }

    public function testCleanStripsTags(): void
    {
        self::assertSame('hello evilworld', Text::clean('<b>hello</b> <script>evil</script>world'));
    }

    public function testCleanTruncates(): void
    {
        $long = str_repeat('a', 300);
        $cleaned = Text::clean($long);

        self::assertSame(256, mb_strlen((string) $cleaned));
    }

    public function testCleanCustomMaxLength(): void
    {
        self::assertSame('hel…', Text::clean('hello', 3));
    }

    public function testCleanRemovesControlCharacters(): void
    {
        self::assertSame('a b', Text::clean("a\x00\x01b"));
    }

    #[DataProvider('moneyProvider')]
    public function testMoney(int $amount, string $expected): void
    {
        self::assertSame($expected, Text::money($amount));
    }

    /** @return iterable<string, array{int, string}> */
    public static function moneyProvider(): iterable
    {
        yield 'zero' => [0, '0.00'];
        yield 'cents' => [99, '0.99'];
        yield 'whole' => [1000, '10.00'];
        yield 'mixed' => [1234, '12.34'];
        yield 'large' => [100050, '1000.50'];
        yield 'negative' => [-500, '-5.00'];
    }

    public function testDateNull(): void
    {
        self::assertNull(Text::date(null));
    }

    public function testDateFormatsAtom(): void
    {
        $date = new \DateTimeImmutable('2026-01-15T10:30:00+00:00');

        self::assertSame('2026-01-15T10:30:00+00:00', Text::date($date));
    }
}
