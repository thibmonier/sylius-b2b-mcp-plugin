<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\CustomerInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\Connection;
use Tmonier\SyliusB2bMcpPlugin\Entity\ConnectionInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\OAuthClient;

final class ConnectionTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(ConnectionInterface::class, new Connection());
    }

    public function testDefaults(): void
    {
        $conn = new Connection();

        self::assertNull($conn->getId());
        self::assertNull($conn->getClient());
        self::assertNull($conn->getCustomer());
        self::assertNull($conn->getChannelCode());
        self::assertSame([], $conn->getScopes());
        self::assertNull($conn->getLastUsedAt());
        self::assertNull($conn->getRevokedAt());
        self::assertTrue($conn->isActive());
        self::assertInstanceOf(\DateTimeImmutable::class, $conn->getCreatedAt());
    }

    public function testSetters(): void
    {
        $conn = new Connection();
        $client = new OAuthClient('id', 'Test', []);
        $customer = $this->createMock(CustomerInterface::class);

        $conn->setClient($client);
        $conn->setCustomer($customer);
        $conn->setChannelCode('WEB');
        $conn->setScopes(['b2b:read', 'b2b:cart']);

        self::assertSame($client, $conn->getClient());
        self::assertSame($customer, $conn->getCustomer());
        self::assertSame('WEB', $conn->getChannelCode());
        self::assertSame(['b2b:read', 'b2b:cart'], $conn->getScopes());
    }

    public function testHasScope(): void
    {
        $conn = new Connection();
        $conn->setScopes(['b2b:read', 'b2b:cart']);

        self::assertTrue($conn->hasScope('b2b:read'));
        self::assertTrue($conn->hasScope('b2b:cart'));
        self::assertFalse($conn->hasScope('b2b:approve'));
    }

    public function testScopesDeduplication(): void
    {
        $conn = new Connection();
        $conn->setScopes(['b2b:read', 'b2b:read', 'b2b:cart']);

        self::assertSame(['b2b:read', 'b2b:cart'], $conn->getScopes());
    }

    public function testRevoke(): void
    {
        $conn = new Connection();
        $now = new \DateTimeImmutable();

        self::assertTrue($conn->isActive());

        $conn->revoke($now);

        self::assertFalse($conn->isActive());
        self::assertSame($now, $conn->getRevokedAt());
    }

    public function testRevokeIsIdempotent(): void
    {
        $conn = new Connection();
        $first = new \DateTimeImmutable('2026-01-01');
        $second = new \DateTimeImmutable('2026-06-01');

        $conn->revoke($first);
        $conn->revoke($second);

        self::assertSame($first, $conn->getRevokedAt());
    }

    public function testLastUsedAt(): void
    {
        $conn = new Connection();
        $now = new \DateTimeImmutable();

        $conn->setLastUsedAt($now);

        self::assertSame($now, $conn->getLastUsedAt());
    }
}
