<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\OAuth\Entity;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Entity\ScopeEntity;

final class ScopeEntityTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(ScopeEntityInterface::class, new ScopeEntity('b2b:read'));
    }

    public function testGetIdentifier(): void
    {
        self::assertSame('b2b:read', (new ScopeEntity('b2b:read'))->getIdentifier());
    }

    public function testJsonSerialize(): void
    {
        self::assertSame('b2b:cart', (new ScopeEntity('b2b:cart'))->jsonSerialize());
    }
}
