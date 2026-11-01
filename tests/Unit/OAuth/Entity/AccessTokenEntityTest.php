<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\OAuth\Entity;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Entity\AccessTokenEntity;
use Tmonier\SyliusB2bMcpPlugin\OAuth\NullCryptKey;

final class AccessTokenEntityTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(AccessTokenEntityInterface::class, new AccessTokenEntity());
    }

    public function testToStringReturnsIdentifier(): void
    {
        $token = new AccessTokenEntity();
        $token->setIdentifier('abc123');

        self::assertSame('abc123', $token->toString());
    }

    public function testSetPrivateKeyIsNoOp(): void
    {
        $token = new AccessTokenEntity();
        $token->setIdentifier('opaque-token');
        $token->setPrivateKey(new NullCryptKey());

        self::assertSame('opaque-token', $token->toString());
    }
}
