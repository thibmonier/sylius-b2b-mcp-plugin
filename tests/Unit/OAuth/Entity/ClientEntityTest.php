<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\OAuth\Entity;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\Entity\OAuthClient;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Entity\ClientEntity;

final class ClientEntityTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $client = new OAuthClient('id-1', 'Claude', ['https://claude.ai/callback']);

        self::assertInstanceOf(ClientEntityInterface::class, new ClientEntity($client));
    }

    public function testDelegatesGetters(): void
    {
        $client = new OAuthClient('id-1', 'Claude', ['https://claude.ai/callback'], 'hash');
        $entity = new ClientEntity($client);

        self::assertSame('id-1', $entity->getIdentifier());
        self::assertSame('Claude', $entity->getName());
        self::assertSame(['https://claude.ai/callback'], $entity->getRedirectUri());
        self::assertTrue($entity->isConfidential());
    }

    public function testPublicClient(): void
    {
        $client = new OAuthClient('id-2', 'Public', ['https://example.com/cb']);
        $entity = new ClientEntity($client);

        self::assertFalse($entity->isConfidential());
    }

    public function testGetClientReturnsUnderlying(): void
    {
        $client = new OAuthClient('id-1', 'Claude', []);
        $entity = new ClientEntity($client);

        self::assertSame($client, $entity->getClient());
    }

    public function testEmptyIdentifierThrows(): void
    {
        $client = new OAuthClient('', 'Empty', []);
        $entity = new ClientEntity($client);

        $this->expectException(\LogicException::class);
        $entity->getIdentifier();
    }
}
