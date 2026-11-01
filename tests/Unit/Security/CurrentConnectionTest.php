<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Security;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Tmonier\SyliusB2bMcpPlugin\Entity\ConnectionInterface;
use Tmonier\SyliusB2bMcpPlugin\Security\AuthenticatedConnection;
use Tmonier\SyliusB2bMcpPlugin\Security\CurrentConnection;

final class CurrentConnectionTest extends TestCase
{
    public function testReturnsNullWhenNoRequest(): void
    {
        $stack = new RequestStack();
        $current = new CurrentConnection($stack);

        self::assertNull($current->get());
        self::assertNull($current->getIpAddress());
    }

    public function testReturnsNullWhenNoAttribute(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/'));
        $current = new CurrentConnection($stack);

        self::assertNull($current->get());
    }

    public function testReturnsConnectionFromAttribute(): void
    {
        $connection = new AuthenticatedConnection(
            $this->createMock(ConnectionInterface::class),
            $this->createMock(ShopUserInterface::class),
            $this->createMock(CustomerInterface::class),
            $this->createMock(ChannelInterface::class),
            ['b2b:read'],
        );

        $request = Request::create('/');
        $request->attributes->set(CurrentConnection::REQUEST_ATTRIBUTE, $connection);

        $stack = new RequestStack();
        $stack->push($request);
        $current = new CurrentConnection($stack);

        self::assertSame($connection, $current->get());
    }

    public function testReturnsIpAddress(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.1']));
        $current = new CurrentConnection($stack);

        self::assertSame('192.168.1.1', $current->getIpAddress());
    }
}
