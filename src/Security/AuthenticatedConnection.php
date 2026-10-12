<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Security;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\ConnectionInterface;

/** The customer behind the bearer token of the current MCP request, on the channel of the request. */
final class AuthenticatedConnection
{
    /** @param list<string> $scopes scopes of the access token */
    public function __construct(
        public readonly ConnectionInterface $connection,
        public readonly ShopUserInterface $shopUser,
        public readonly CustomerInterface $customer,
        public readonly ChannelInterface $channel,
        public readonly array $scopes,
    ) {
    }

    public function hasScope(string $scope): bool
    {
        return \in_array($scope, $this->scopes, true);
    }
}
