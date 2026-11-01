<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Cart;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\Cart\CartLine;

final class CartLineTest extends TestCase
{
    public function testConstructor(): void
    {
        $line = new CartLine('SKU-001', 3);

        self::assertSame('SKU-001', $line->code);
        self::assertSame(3, $line->quantity);
    }

    public function testIsReadonly(): void
    {
        $ref = new \ReflectionClass(CartLine::class);

        self::assertTrue($ref->isReadOnly());
    }
}
