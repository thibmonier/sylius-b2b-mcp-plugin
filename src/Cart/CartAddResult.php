<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Cart;

final readonly class CartAddResult
{
    public const REFUSED_CART_LOCKED = 'cart_locked';

    /** @param list<CartLineResult> $lines */
    public function __construct(
        public array $lines,
        public bool $applied,
        public ?string $refusal = null,
    ) {
    }
}
