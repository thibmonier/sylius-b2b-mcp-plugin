<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tmonier\SyliusB2bMcpPlugin\Cart\QuickOrderCartLinesAdder;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\QuickOrderAddTool;

/** Loaded when tmonier/sylius-quick-order-plugin is enabled: quick_order_add, and reorder with the quick order rules. */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('tmonier_b2b_mcp.cart.quick_order_adder', QuickOrderCartLinesAdder::class)
        ->args([service('tmonier_quick_order.bulk_cart_adder'), param('tmonier_quick_order.max_lines')]);
    $services->alias('tmonier_b2b_mcp.cart.adder', 'tmonier_b2b_mcp.cart.quick_order_adder');

    $services->set(QuickOrderAddTool::class)
        ->args([
            service('tmonier_b2b_mcp.mcp.tool_runner'),
            service('tmonier_b2b_mcp.cart.provider'),
            service('tmonier_b2b_mcp.cart.adder'),
            service('tmonier_b2b_mcp.order.presenter'),
            service('tmonier_b2b_mcp.order.shop_url_generator'),
            param('tmonier_quick_order.max_lines'),
            param('tmonier_quick_order.default_mode'),
        ])
        ->tag('mcp.tool')
        ->tag('tmonier_b2b_mcp.tool');
};
