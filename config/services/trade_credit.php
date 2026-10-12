<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\GetTradeCreditStatementTool;

/** Loaded when tmonier/sylius-trade-credit-plugin is enabled. */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set(GetTradeCreditStatementTool::class)
        ->args([
            service('tmonier_b2b_mcp.mcp.tool_runner'),
            service('tmonier_trade_credit.statement_builder'),
            service('clock'),
        ])
        ->tag('mcp.tool')
        ->tag('tmonier_b2b_mcp.tool');
};
