<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\Entity\Connection;
use Tmonier\SyliusB2bMcpPlugin\Entity\OAuthToken;

final class OAuthTokenTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $connection = new Connection();
        $expires = new \DateTimeImmutable('+1 hour');

        $token = new OAuthToken(
            OAuthToken::TYPE_ACCESS,
            'hash_abc',
            $connection,
            ['b2b:read'],
            $expires,
            'parent_hash',
        );

        self::assertNull($token->getId());
        self::assertSame(OAuthToken::TYPE_ACCESS, $token->getType());
        self::assertSame('hash_abc', $token->getIdentifierHash());
        self::assertSame($connection, $token->getConnection());
        self::assertSame(['b2b:read'], $token->getScopes());
        self::assertSame($expires, $token->getExpiresAt());
        self::assertSame('parent_hash', $token->getParentHash());
        self::assertNull($token->getRevokedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $token->getCreatedAt());
    }

    public function testHashIsSha256(): void
    {
        self::assertSame(hash('sha256', 'secret'), OAuthToken::hash('secret'));
        self::assertSame(64, \strlen(OAuthToken::hash('test')));
    }

    public function testIsUsableWhenValid(): void
    {
        $connection = new Connection();
        $token = new OAuthToken(OAuthToken::TYPE_ACCESS, 'h', $connection, [], new \DateTimeImmutable('+1 hour'));

        self::assertTrue($token->isUsableAt(new \DateTimeImmutable()));
    }

    public function testIsNotUsableWhenExpired(): void
    {
        $connection = new Connection();
        $token = new OAuthToken(OAuthToken::TYPE_ACCESS, 'h', $connection, [], new \DateTimeImmutable('-1 hour'));

        self::assertFalse($token->isUsableAt(new \DateTimeImmutable()));
    }

    public function testIsNotUsableWhenRevoked(): void
    {
        $connection = new Connection();
        $token = new OAuthToken(OAuthToken::TYPE_ACCESS, 'h', $connection, [], new \DateTimeImmutable('+1 hour'));

        $token->revoke(new \DateTimeImmutable());

        self::assertFalse($token->isUsableAt(new \DateTimeImmutable()));
    }

    public function testIsNotUsableWhenConnectionRevoked(): void
    {
        $connection = new Connection();
        $connection->revoke(new \DateTimeImmutable());
        $token = new OAuthToken(OAuthToken::TYPE_ACCESS, 'h', $connection, [], new \DateTimeImmutable('+1 hour'));

        self::assertFalse($token->isUsableAt(new \DateTimeImmutable()));
    }

    public function testRevokeIsIdempotent(): void
    {
        $connection = new Connection();
        $token = new OAuthToken(OAuthToken::TYPE_ACCESS, 'h', $connection, [], new \DateTimeImmutable('+1 hour'));

        $first = new \DateTimeImmutable('2026-01-01');
        $second = new \DateTimeImmutable('2026-06-01');

        $token->revoke($first);
        $token->revoke($second);

        self::assertSame($first, $token->getRevokedAt());
    }

    public function testTypeConstants(): void
    {
        self::assertSame('access', OAuthToken::TYPE_ACCESS);
        self::assertSame('refresh', OAuthToken::TYPE_REFRESH);
        self::assertSame('code', OAuthToken::TYPE_AUTH_CODE);
    }
}
