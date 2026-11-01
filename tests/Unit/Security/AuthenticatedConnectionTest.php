<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Security;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\ConnectionInterface;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Scopes;
use Tmonier\SyliusB2bMcpPlugin\Security\AuthenticatedConnection;

final class AuthenticatedConnectionTest extends TestCase
{
    public function testHasScope(): void
    {
        $connection = new AuthenticatedConnection(
            $this->createMock(ConnectionInterface::class),
            $this->createMock(ShopUserInterface::class),
            $this->createMock(CustomerInterface::class),
            $this->createMock(ChannelInterface::class),
            [Scopes::READ, Scopes::CART],
        );

        self::assertTrue($connection->hasScope(Scopes::READ));
        self::assertTrue($connection->hasScope(Scopes::CART));
        self::assertFalse($connection->hasScope(Scopes::APPROVE));
    }
}
