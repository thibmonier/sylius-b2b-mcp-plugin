<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Cart;

/** A requested line: variant code (SKU) and quantity. */
final readonly class CartLine
{
    public function __construct(
        public string $code,
        public int $quantity,
    ) {
    }
}
