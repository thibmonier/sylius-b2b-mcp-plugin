<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AccountMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $event->getMenu()
            ->addChild('tmonier_b2b_mcp', ['route' => 'tmonier_b2b_mcp_shop_connections'])
            ->setLabel('tmonier_b2b_mcp.ui.ai_assistants')
            ->setLabelAttribute('icon', 'tabler:robot')
        ;
    }
}
