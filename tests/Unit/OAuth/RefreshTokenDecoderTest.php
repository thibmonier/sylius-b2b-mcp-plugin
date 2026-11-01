<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\OAuth;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\OAuth\RefreshTokenDecoder;

final class RefreshTokenDecoderTest extends TestCase
{
    public function testReturnsNullForGarbageInput(): void
    {
        $decoder = new RefreshTokenDecoder(str_repeat('a', 32));

        self::assertNull($decoder->getIdentifier('not-a-valid-token'));
    }

    public function testReturnsNullForEmptyString(): void
    {
        $decoder = new RefreshTokenDecoder(str_repeat('a', 32));

        self::assertNull($decoder->getIdentifier(''));
    }
}
