<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tmonier\SyliusB2bMcpPlugin\TmonierSyliusB2bMcpPlugin;

final class TmonierSyliusB2bMcpPluginTest extends TestCase
{
    public function testIsBundle(): void
    {
        self::assertInstanceOf(Bundle::class, new TmonierSyliusB2bMcpPlugin());
    }

    public function testGetPathPointsToRoot(): void
    {
        $plugin = new TmonierSyliusB2bMcpPlugin();

        self::assertSame(\dirname(__DIR__, 2), $plugin->getPath());
    }
}
