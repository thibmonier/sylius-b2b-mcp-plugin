<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Cart;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\Cart\CartAddResult;
use Tmonier\SyliusB2bMcpPlugin\Cart\CartLineResult;

final class CartAddResultTest extends TestCase
{
    public function testAppliedResult(): void
    {
        $lines = [new CartLineResult('SKU-001', 2, CartLineResult::ADDED, 2)];
        $result = new CartAddResult($lines, true);

        self::assertSame($lines, $result->lines);
        self::assertTrue($result->applied);
        self::assertNull($result->refusal);
    }

    public function testRefusedResult(): void
    {
        $result = new CartAddResult([], false, CartAddResult::REFUSED_CART_LOCKED);

        self::assertSame([], $result->lines);
        self::assertFalse($result->applied);
        self::assertSame('cart_locked', $result->refusal);
    }
}
