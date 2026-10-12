<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\ListMyQuotesTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\RequestQuoteTool;

/** Loaded when tmonier/sylius-quote-request-plugin is enabled. */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(RequestQuoteTool::class)
        ->args([
            service('tmonier_b2b_mcp.mcp.tool_runner'),
            service('tmonier_quote_request.factory'),
            service('tmonier_quote_request.manager'),
            service('tmonier_b2b_mcp.cart.provider'),
            service('sylius.repository.product_variant'),
            service('tmonier_b2b_mcp.order.shop_url_generator'),
            param('tmonier_quote_request.from_cart'),
            param('tmonier_quote_request.max_lines'),
            service('limiter.tmonier_quote_request_email')->nullOnInvalid(),
        ])
        ->tag('mcp.tool')
        ->tag('tmonier_b2b_mcp.tool');

    $services->set(ListMyQuotesTool::class)
        ->args([
            service('tmonier_b2b_mcp.mcp.tool_runner'),
            service('tmonier_quote_request.repository.quote_request'),
            service('tmonier_b2b_mcp.order.shop_url_generator'),
        ])
        ->tag('mcp.tool')
        ->tag('tmonier_b2b_mcp.tool');
};
