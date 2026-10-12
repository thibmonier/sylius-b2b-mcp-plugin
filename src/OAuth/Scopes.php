<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth;

/**
 * OAuth scopes of the B2B tools. A client that asks for no scope gets the default ones;
 * b2b:approve is never granted by default and only exists when approval decisions are enabled.
 */
final class Scopes
{
    /** Orders, quotes, trade credit statement, pending approvals, checkout link. */
    public const READ = 'b2b:read';

    /** Adds products to the cart and sends quote requests (never places an order). */
    public const CART = 'b2b:cart';

    /** Approves or rejects purchase orders of the company (opt-in, purchase approval plugin). */
    public const APPROVE = 'b2b:approve';

    public const DEFAULT = [self::READ, self::CART];

    public const ALL = [self::READ, self::CART, self::APPROVE];

    /**
     * @param list<string> $requested
     *
     * @return list<string>
     */
    public static function normalize(array $requested): array
    {
        return array_values(array_filter(self::ALL, static fn (string $scope): bool => \in_array($scope, $requested, true)));
    }

    private function __construct()
    {
    }
}
