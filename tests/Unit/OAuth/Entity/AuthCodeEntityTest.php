<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\OAuth\Entity;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Entity\AuthCodeEntity;

final class AuthCodeEntityTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(AuthCodeEntityInterface::class, new AuthCodeEntity());
    }

    public function testRedirectUri(): void
    {
        $code = new AuthCodeEntity();
        $code->setRedirectUri('https://example.com/callback');

        self::assertSame('https://example.com/callback', $code->getRedirectUri());
    }
}
