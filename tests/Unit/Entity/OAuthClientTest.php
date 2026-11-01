<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\Entity\OAuthClient;

final class OAuthClientTest extends TestCase
{
    public function testConstructor(): void
    {
        $client = new OAuthClient('client-1', 'Claude', ['https://claude.ai/cb'], 'hash123');

        self::assertNull($client->getId());
        self::assertSame('client-1', $client->getIdentifier());
        self::assertSame('Claude', $client->getName());
        self::assertSame(['https://claude.ai/cb'], $client->getRedirectUris());
        self::assertSame('hash123', $client->getSecretHash());
        self::assertTrue($client->isConfidential());
        self::assertTrue($client->isDynamicallyRegistered());
        self::assertInstanceOf(\DateTimeImmutable::class, $client->getCreatedAt());
    }

    public function testPublicClient(): void
    {
        $client = new OAuthClient('client-2', 'Public', ['https://example.com']);

        self::assertNull($client->getSecretHash());
        self::assertFalse($client->isConfidential());
    }

    public function testStaticRegistration(): void
    {
        $client = new OAuthClient('client-3', 'Static', [], null, false);

        self::assertFalse($client->isDynamicallyRegistered());
    }
}
