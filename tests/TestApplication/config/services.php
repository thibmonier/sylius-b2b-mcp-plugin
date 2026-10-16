<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tests\Tmonier\SyliusB2bMcpPlugin\Account\SwitchableChecker;

/*
 * Test application only: the core cart adder is replaced by the quick order one when
 * tmonier/sylius-quick-order-plugin is enabled (as here); keep it reachable for its own tests.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->alias('tests.tmonier_b2b_mcp.cart.core_adder', 'tmonier_b2b_mcp.cart.core_adder')->public();

    // Two account usability checkers (autoconfigured and tagged by the plugin), driven by the tests.
    $services->set('tests.account_usability.first', SwitchableChecker::class)->autoconfigure()->public();
    $services->set('tests.account_usability.second', SwitchableChecker::class)->autoconfigure()->public();
};
