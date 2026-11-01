<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Menu;

use Knp\Menu\FactoryInterface;
use Knp\Menu\MenuItem;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Tmonier\SyliusB2bMcpPlugin\Menu\AccountMenuListener;

final class AccountMenuListenerTest extends TestCase
{
    public function testAddsMenuItem(): void
    {
        $factory = $this->createMock(FactoryInterface::class);
        $factory->method('createItem')->willReturnCallback(fn (string $name, array $options = []) => new MenuItem($name, $factory));
        $menu = new MenuItem('account', $factory);
        $event = new MenuBuilderEvent($factory, $menu);

        (new AccountMenuListener())($event);

        $child = $menu->getChild('tmonier_b2b_mcp');
        self::assertNotNull($child);
    }
}
