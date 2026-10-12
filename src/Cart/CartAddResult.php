<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Cart;

final class CartAddResult
{
    public const REFUSED_CART_LOCKED = 'cart_locked';

    /** @param list<CartLineResult> $lines */
    public function __construct(
        public readonly array $lines,
        public readonly bool $applied,
        public readonly ?string $refusal = null,
    ) {
    }
}
