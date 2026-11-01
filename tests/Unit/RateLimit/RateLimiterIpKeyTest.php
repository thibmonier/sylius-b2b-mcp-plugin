<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\RateLimit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\RateLimit\RateLimiter;

final class RateLimiterIpKeyTest extends TestCase
{
    #[DataProvider('ipKeysProvider')]
    public function testIpKey(?string $ip, string $expected): void
    {
        self::assertSame($expected, RateLimiter::ipKey($ip));
    }

    /** @return iterable<string, array{?string, string}> */
    public static function ipKeysProvider(): iterable
    {
        yield 'null' => [null, 'unknown'];
        yield 'empty' => ['', 'unknown'];
        yield 'ipv4' => ['192.168.1.1', '192.168.1.1'];
        yield 'ipv4 localhost' => ['127.0.0.1', '127.0.0.1'];
    }
}
