<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\OAuth;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\OAuth\NullCryptKey;

final class NullCryptKeyTest extends TestCase
{
    public function testReturnsEmptyValues(): void
    {
        $key = new NullCryptKey();

        self::assertSame('', $key->getKeyPath());
        self::assertNull($key->getPassPhrase());
        self::assertSame('', $key->getKeyContents());
    }
}
