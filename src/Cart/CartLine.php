<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Cart;

/** A requested line: variant code (SKU) and quantity. */
final class CartLine
{
    public function __construct(
        public readonly string $code,
        public readonly int $quantity,
    ) {
    }
}
