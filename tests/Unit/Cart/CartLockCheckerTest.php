<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Cart;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Tmonier\SyliusB2bMcpPlugin\Cart\CartLockChecker;

final class CartLockCheckerTest extends TestCase
{
    public function testNotLockedWithoutChecker(): void
    {
        $checker = new CartLockChecker(null);
        $cart = $this->createMock(OrderInterface::class);
        $cart->method('getId')->willReturn(1);

        self::assertFalse($checker->isLocked($cart));
    }

    public function testNotLockedWhenCartHasNoId(): void
    {
        $quoteChecker = new class() {
            public function isQuoteCart(OrderInterface $cart): bool
            {
                return true;
            }
        };
        $checker = new CartLockChecker($quoteChecker);
        $cart = $this->createMock(OrderInterface::class);
        $cart->method('getId')->willReturn(null);

        self::assertFalse($checker->isLocked($cart));
    }

    public function testLockedWhenQuoteCart(): void
    {
        $quoteChecker = new class() {
            public function isQuoteCart(OrderInterface $cart): bool
            {
                return true;
            }
        };
        $checker = new CartLockChecker($quoteChecker);
        $cart = $this->createMock(OrderInterface::class);
        $cart->method('getId')->willReturn(42);

        self::assertTrue($checker->isLocked($cart));
    }

    public function testNotLockedWhenNotQuoteCart(): void
    {
        $quoteChecker = new class() {
            public function isQuoteCart(OrderInterface $cart): bool
            {
                return false;
            }
        };
        $checker = new CartLockChecker($quoteChecker);
        $cart = $this->createMock(OrderInterface::class);
        $cart->method('getId')->willReturn(42);

        self::assertFalse($checker->isLocked($cart));
    }
}
