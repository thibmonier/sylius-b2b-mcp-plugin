<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Cart;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\Cart\CartLineResult;

final class CartLineResultTest extends TestCase
{
    #[DataProvider('errorStatusProvider')]
    public function testIsError(string $status, bool $expected): void
    {
        $result = new CartLineResult('SKU-001', 5, $status);

        self::assertSame($expected, $result->isError());
    }

    /** @return iterable<string, array{string, bool}> */
    public static function errorStatusProvider(): iterable
    {
        yield 'valid' => [CartLineResult::VALID, false];
        yield 'added' => [CartLineResult::ADDED, false];
        yield 'adjusted' => [CartLineResult::ADJUSTED, false];
        yield 'skipped' => [CartLineResult::SKIPPED, false];
        yield 'unknown_code' => [CartLineResult::UNKNOWN_CODE, true];
        yield 'disabled' => [CartLineResult::DISABLED, true];
        yield 'not_in_channel' => [CartLineResult::NOT_IN_CHANNEL, true];
        yield 'no_channel_pricing' => [CartLineResult::NO_CHANNEL_PRICING, true];
        yield 'insufficient_stock' => [CartLineResult::INSUFFICIENT_STOCK, true];
        yield 'quantity_limit_exceeded' => [CartLineResult::QUANTITY_LIMIT_EXCEEDED, true];
        yield 'invalid_quantity' => [CartLineResult::INVALID_QUANTITY, true];
        yield 'removed' => [CartLineResult::REMOVED, true];
    }

    public function testConstructorFields(): void
    {
        $result = new CartLineResult('SKU-002', 3, CartLineResult::ADDED, 3, 'Widget', 'Red Widget', 10);

        self::assertSame('SKU-002', $result->code);
        self::assertSame(3, $result->requestedQuantity);
        self::assertSame(CartLineResult::ADDED, $result->status);
        self::assertSame(3, $result->addedQuantity);
        self::assertSame('Widget', $result->productName);
        self::assertSame('Red Widget', $result->variantName);
        self::assertSame(10, $result->available);
    }

    public function testDefaultValues(): void
    {
        $result = new CartLineResult('SKU-003', 1, CartLineResult::VALID);

        self::assertSame(0, $result->addedQuantity);
        self::assertNull($result->productName);
        self::assertNull($result->variantName);
        self::assertNull($result->available);
    }
}
