<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Menu;

use Knp\Menu\FactoryInterface;
use Knp\Menu\MenuItem;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Tmonier\SyliusB2bMcpPlugin\Menu\AdminMenuListener;

final class AdminMenuListenerTest extends TestCase
{
    public function testAddsMenuItemsUnderCustomers(): void
    {
        $factory = $this->createMock(FactoryInterface::class);
        $factory->method('createItem')->willReturnCallback(fn (string $name, array $options = []) => new MenuItem($name, $factory));
        $menu = new MenuItem('root', $factory);
        $customers = new MenuItem('customers', $factory);
        $menu->addChild($customers);

        $event = new MenuBuilderEvent($factory, $menu);
        (new AdminMenuListener())($event);

        self::assertNotNull($customers->getChild('tmonier_b2b_mcp_connections'));
        self::assertNotNull($customers->getChild('tmonier_b2b_mcp_tool_calls'));
    }

    public function testFallsBackToRootWhenNoCustomersSection(): void
    {
        $factory = $this->createMock(FactoryInterface::class);
        $factory->method('createItem')->willReturnCallback(fn (string $name, array $options = []) => new MenuItem($name, $factory));
        $menu = new MenuItem('root', $factory);

        $event = new MenuBuilderEvent($factory, $menu);
        (new AdminMenuListener())($event);

        self::assertNotNull($menu->getChild('tmonier_b2b_mcp_connections'));
        self::assertNotNull($menu->getChild('tmonier_b2b_mcp_tool_calls'));
    }
}
