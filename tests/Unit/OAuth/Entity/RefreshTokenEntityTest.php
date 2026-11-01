<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\OAuth\Entity;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Entity\RefreshTokenEntity;

final class RefreshTokenEntityTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(RefreshTokenEntityInterface::class, new RefreshTokenEntity());
    }

    public function testAccessToken(): void
    {
        $refresh = new RefreshTokenEntity();
        $access = $this->createMock(AccessTokenEntityInterface::class);

        $refresh->setAccessToken($access);

        self::assertSame($access, $refresh->getAccessToken());
    }

    public function testExpiresAt(): void
    {
        $refresh = new RefreshTokenEntity();
        $date = new \DateTimeImmutable('2026-12-31');

        $refresh->setExpiryDateTime($date);

        self::assertSame($date, $refresh->getExpiryDateTime());
    }
}
