<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();
        $parent = $menu->getChild('customers') ?? $menu;

        $parent
            ->addChild('tmonier_b2b_mcp_connections', ['route' => 'tmonier_b2b_mcp_admin_connection_index'])
            ->setLabel('tmonier_b2b_mcp.ui.ai_assistant_connections')
            ->setLabelAttribute('icon', 'tabler:robot')
        ;
        $parent
            ->addChild('tmonier_b2b_mcp_tool_calls', ['route' => 'tmonier_b2b_mcp_admin_tool_call_index'])
            ->setLabel('tmonier_b2b_mcp.ui.tool_calls')
            ->setLabelAttribute('icon', 'tabler:list-search')
        ;
    }
}
