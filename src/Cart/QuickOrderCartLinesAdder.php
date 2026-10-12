<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Cart;

use Sylius\Component\Core\Model\OrderInterface;
use Tmonier\SyliusQuickOrderPlugin\Adder\BulkAddMode;
use Tmonier\SyliusQuickOrderPlugin\Adder\BulkAddResult;
use Tmonier\SyliusQuickOrderPlugin\Adder\BulkCartAdderInterface;
use Tmonier\SyliusQuickOrderPlugin\Adder\LineResult;
use Tmonier\SyliusQuickOrderPlugin\Input\LineCollector;

/** Same validation and pack-size rounding as the quick order page (tmonier/sylius-quick-order-plugin). */
final class QuickOrderCartLinesAdder implements CartLinesAdderInterface
{
    public function __construct(
        private readonly BulkCartAdderInterface $bulkCartAdder,
        private readonly int $maxLines,
    ) {
    }

    public function preview(OrderInterface $cart, array $lines): CartAddResult
    {
        return self::convert($this->bulkCartAdder->validate($cart, $this->input($lines)));
    }

    public function add(OrderInterface $cart, array $lines, bool $allOrNothing = false): CartAddResult
    {
        return self::convert($this->bulkCartAdder->add($cart, $this->input($lines), $allOrNothing ? BulkAddMode::AllOrNothing : BulkAddMode::Partial));
    }

    /** @param list<CartLine> $lines */
    private function input(array $lines): \Tmonier\SyliusQuickOrderPlugin\Input\QuickOrderInput
    {
        $collector = new LineCollector($this->maxLines);
        foreach ($lines as $index => $line) {
            $collector->add($index + 1, $line->code, $line->quantity, true);
        }

        return $collector->getInput();
    }

    private static function convert(BulkAddResult $result): CartAddResult
    {
        return new CartAddResult(
            array_map(static fn (LineResult $line): CartLineResult => new CartLineResult(
                $line->code,
                (int) $line->requestedQuantity,
                $line->status->value,
                $line->addedQuantity,
                $line->productName,
                $line->variantName,
                $line->available,
            ), $result->lines),
            $result->applied,
            $result->refusal,
        );
    }
}
