<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\OAuth\Entity;

use League\OAuth2\Server\Entities\UserEntityInterface;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Entity\UserEntity;

final class UserEntityTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(UserEntityInterface::class, new UserEntity('conn-42'));
    }

    public function testGetIdentifier(): void
    {
        self::assertSame('conn-42', (new UserEntity('conn-42'))->getIdentifier());
    }
}
