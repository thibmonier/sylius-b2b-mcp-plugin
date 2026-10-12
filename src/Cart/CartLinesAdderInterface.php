<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Cart;

use Sylius\Component\Core\Model\OrderInterface;

/**
 * Adds lines to a cart with the rules of the shop. The quick order implementation (same
 * validation as the quick order page) replaces the core one when that plugin is installed.
 */
interface CartLinesAdderInterface
{
    /** @param list<CartLine> $lines */
    public function preview(OrderInterface $cart, array $lines): CartAddResult;

    /**
     * Adds the valid lines (one order processing, one flush), then reports each line as
     * computed after the processing.
     *
     * @param list<CartLine> $lines
     */
    public function add(OrderInterface $cart, array $lines, bool $allOrNothing = false): CartAddResult;
}
