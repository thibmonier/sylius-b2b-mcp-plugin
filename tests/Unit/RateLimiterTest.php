<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\RateLimit\RateLimiter;

final class RateLimiterTest extends TestCase
{
    public function testIpKeys(): void
    {
        self::assertSame('203.0.113.7', RateLimiter::ipKey('203.0.113.7'));
        self::assertSame('203.0.113.7', RateLimiter::ipKey('::ffff:203.0.113.7'));
        self::assertSame('2001:db8:1234:5600::/56', RateLimiter::ipKey('2001:db8:1234:5678:abcd::1'));
        self::assertSame(RateLimiter::ipKey('2001:db8:1234:56ff::1'), RateLimiter::ipKey('2001:db8:1234:5601::9'));
        self::assertSame('unknown', RateLimiter::ipKey(null));
    }
}
