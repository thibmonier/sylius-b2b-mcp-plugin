<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Cart;

use Sylius\Component\Core\Model\OrderInterface;

/** A cart holding an accepted quote (tmonier/sylius-quote-request-plugin) keeps its negotiated lines. */
final class CartLockChecker
{
    public function __construct(private readonly ?object $quoteCartChecker = null)
    {
    }

    public function isLocked(OrderInterface $cart): bool
    {
        if (null === $this->quoteCartChecker || null === $cart->getId() || !method_exists($this->quoteCartChecker, 'isQuoteCart')) {
            return false;
        }

        return true === $this->quoteCartChecker->isQuoteCart($cart);
    }
}
