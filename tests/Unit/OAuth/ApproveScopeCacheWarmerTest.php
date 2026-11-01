<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\OAuth;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Tmonier\SyliusB2bMcpPlugin\OAuth\ApproveScopeCacheWarmer;

final class ApproveScopeCacheWarmerTest extends TestCase
{
    public function testIsOptionalCacheWarmer(): void
    {
        $ref = new \ReflectionClass(ApproveScopeCacheWarmer::class);

        self::assertTrue($ref->implementsInterface(CacheWarmerInterface::class));
    }
}
