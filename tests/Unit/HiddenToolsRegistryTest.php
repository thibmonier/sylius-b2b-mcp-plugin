<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit;

use Mcp\Capability\Registry;
use Mcp\Schema\Tool;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\DependencyInjection\Configuration;
use Tmonier\SyliusB2bMcpPlugin\Mcp\HiddenToolsRegistry;

final class HiddenToolsRegistryTest extends TestCase
{
    public function testTheHiddenToolsAreNotRegistered(): void
    {
        $inner = new Registry();
        $registry = new HiddenToolsRegistry($inner, Configuration::DEFAULT_HIDDEN_OFFICIAL_TOOLS);

        foreach (['complete_checkout', 'search_products', 'select_payment_method'] as $name) {
            $registry->registerTool(new Tool($name, ['type' => 'object', 'properties' => []], null, null), 'strlen');
        }

        self::assertSame(['search_products'], array_values(array_map(static fn (object $tool): string => $tool instanceof Tool ? $tool->name : '', $registry->getTools()->references)));
    }
}
