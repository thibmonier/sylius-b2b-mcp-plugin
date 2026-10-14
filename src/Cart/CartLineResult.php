<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Cart;

/** Outcome of one line; statuses follow tmonier/sylius-quick-order-plugin. */
final readonly class CartLineResult
{
    public const VALID = 'valid';

    public const ADDED = 'added';

    public const ADJUSTED = 'adjusted';

    public const REMOVED = 'removed';

    public const SKIPPED = 'skipped';

    public const UNKNOWN_CODE = 'unknown_code';

    public const DISABLED = 'disabled';

    public const NOT_IN_CHANNEL = 'not_in_channel';

    public const NO_CHANNEL_PRICING = 'no_channel_pricing';

    public const INSUFFICIENT_STOCK = 'insufficient_stock';

    public const QUANTITY_LIMIT_EXCEEDED = 'quantity_limit_exceeded';

    public const INVALID_QUANTITY = 'invalid_quantity';

    public function __construct(
        public string $code,
        public int $requestedQuantity,
        public string $status,
        public int $addedQuantity = 0,
        public ?string $productName = null,
        public ?string $variantName = null,
        public ?int $available = null,
    ) {
    }

    public function isError(): bool
    {
        return !\in_array($this->status, [self::VALID, self::ADDED, self::ADJUSTED, self::SKIPPED], true);
    }
}
