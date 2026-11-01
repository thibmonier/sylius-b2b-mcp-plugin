<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Mcp;

use Mcp\Capability\Registry;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\Mcp\B2bToolLoader;

final class B2bToolLoaderTest extends TestCase
{
    public function testEmptyLoader(): void
    {
        $loader = new B2bToolLoader([]);
        $registry = new Registry();

        $loader->load($registry);

        self::assertCount(0, $registry->getTools()->references);
    }
}
