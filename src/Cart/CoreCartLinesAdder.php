<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Cart;

use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;

/** Core Sylius rules (no quick order plugin): enabled, sold in the channel, priced, in stock, quantity limit. */
final class CoreCartLinesAdder implements CartLinesAdderInterface
{
    /**
     * @param RepositoryInterface<ProductVariantInterface> $variantRepository
     * @param FactoryInterface<OrderItemInterface> $cartItemFactory
     */
    public function __construct(
        private readonly RepositoryInterface $variantRepository,
        private readonly AvailabilityCheckerInterface $availabilityChecker,
        private readonly FactoryInterface $cartItemFactory,
        private readonly OrderItemQuantityModifierInterface $quantityModifier,
        private readonly OrderProcessorInterface $orderProcessor,
        private readonly ObjectManager $orderManager,
        private readonly CartLockChecker $lockChecker,
        private readonly int $quantityLimit,
    ) {
    }

    public function preview(OrderInterface $cart, array $lines): CartAddResult
    {
        if ($this->lockChecker->isLocked($cart)) {
            return new CartAddResult([], false, CartAddResult::REFUSED_CART_LOCKED);
        }

        return new CartAddResult(array_map(static fn (array $check): CartLineResult => $check[0], $this->check($cart, $lines)), false);
    }

    public function add(OrderInterface $cart, array $lines, bool $allOrNothing = false): CartAddResult
    {
        if ($this->lockChecker->isLocked($cart)) {
            return new CartAddResult([], false, CartAddResult::REFUSED_CART_LOCKED);
        }

        $checks = $this->check($cart, $lines);
        $hasErrors = [] !== array_filter($checks, static fn (array $check): bool => $check[0]->isError());
        $valid = array_filter($checks, static fn (array $check): bool => null !== $check[1]);
        if ([] === $valid || ($allOrNothing && $hasErrors)) {
            return new CartAddResult(array_map(static fn (array $check): CartLineResult => null === $check[1] ? $check[0] : self::with($check[0], CartLineResult::SKIPPED), $checks), false);
        }

        $before = self::quantities($cart);
        foreach ($valid as [$line, $variant]) {
            \assert($variant instanceof ProductVariantInterface);
            $existing = self::findItem($cart, $variant);
            if (null !== $existing) {
                $this->quantityModifier->modify($existing, $existing->getQuantity() + $line->requestedQuantity);

                continue;
            }
            $item = $this->cartItemFactory->createNew();
            $item->setVariant($variant);
            $this->quantityModifier->modify($item, $line->requestedQuantity);
            $cart->addItem($item);
        }

        $this->orderProcessor->process($cart);
        $after = self::quantities($cart);

        $results = [];
        foreach ($checks as [$line, $variant]) {
            if (null === $variant) {
                $results[] = $line;

                continue;
            }
            $previous = $before[$line->code] ?? 0;
            $actual = $after[$line->code] ?? 0;
            $results[] = match (true) {
                $actual >= $previous + $line->requestedQuantity => self::with($line, CartLineResult::ADDED, $line->requestedQuantity),
                $actual > $previous => self::with($line, CartLineResult::ADJUSTED, $actual - $previous),
                default => self::with($line, CartLineResult::REMOVED),
            };
        }

        $this->orderManager->persist($cart);
        $this->orderManager->flush();

        return new CartAddResult($results, true);
    }

    /**
     * @param list<CartLine> $lines
     *
     * @return list<array{CartLineResult, ProductVariantInterface|null}> the variant when the line is valid
     */
    private function check(OrderInterface $cart, array $lines): array
    {
        $channel = $cart->getChannel();
        $inCart = self::quantities($cart);
        $checks = [];
        foreach (self::merge($lines) as $line) {
            $variant = $this->variantRepository->findOneBy(['code' => $line->code]);
            if (!$variant instanceof ProductVariantInterface) {
                $checks[] = [new CartLineResult($line->code, $line->quantity, CartLineResult::UNKNOWN_CODE), null];

                continue;
            }
            $product = $variant->getProduct();
            $result = static fn (string $status, ?int $available = null): CartLineResult => new CartLineResult($line->code, $line->quantity, $status, 0, $product?->getName(), $variant->getName(), $available);
            $total = ($inCart[$line->code] ?? 0) + $line->quantity;

            $status = match (true) {
                $line->quantity < 1 => CartLineResult::INVALID_QUANTITY,
                !$variant->isEnabled() || null === $product || !$product->isEnabled() => CartLineResult::DISABLED,
                !$channel instanceof ChannelInterface || !$product->hasChannel($channel) => CartLineResult::NOT_IN_CHANNEL,
                null === $variant->getChannelPricingForChannel($channel) => CartLineResult::NO_CHANNEL_PRICING,
                $total > $this->quantityLimit => CartLineResult::QUANTITY_LIMIT_EXCEEDED,
                !$this->availabilityChecker->isStockSufficient($variant, $total) => CartLineResult::INSUFFICIENT_STOCK,
                default => CartLineResult::VALID,
            };
            $available = CartLineResult::INSUFFICIENT_STOCK === $status ? max(0, ($variant->getOnHand() ?? 0) - ($variant->getOnHold() ?? 0) - ($inCart[$line->code] ?? 0)) : null;
            $checks[] = [$result($status, $available), CartLineResult::VALID === $status ? $variant : null];
        }

        return $checks;
    }

    /**
     * Lines with the same code are merged.
     *
     * @param list<CartLine> $lines
     *
     * @return list<CartLine>
     */
    private static function merge(array $lines): array
    {
        $merged = [];
        foreach ($lines as $line) {
            $code = trim($line->code);
            $merged[$code] = new CartLine($code, ($merged[$code]->quantity ?? 0) + $line->quantity);
        }

        return array_values($merged);
    }

    /** @return array<string, int> quantity by variant code */
    private static function quantities(OrderInterface $cart): array
    {
        $quantities = [];
        foreach ($cart->getItems() as $item) {
            /** @var OrderItemInterface $item */
            $code = $item->getVariant()?->getCode();
            if (null !== $code) {
                $quantities[$code] = ($quantities[$code] ?? 0) + $item->getQuantity();
            }
        }

        return $quantities;
    }

    private static function findItem(OrderInterface $cart, ProductVariantInterface $variant): ?OrderItemInterface
    {
        foreach ($cart->getItems() as $item) {
            /** @var OrderItemInterface $item */
            if ($item->getVariant() === $variant && !$item->isImmutable()) {
                return $item;
            }
        }

        return null;
    }

    private static function with(CartLineResult $line, string $status, int $added = 0): CartLineResult
    {
        return new CartLineResult($line->code, $line->requestedQuantity, $status, $added, $line->productName, $line->variantName, $line->available);
    }
}
