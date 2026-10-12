<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

/*
 * Test application only: the core cart adder is replaced by the quick order one when
 * tmonier/sylius-quick-order-plugin is enabled (as here); keep it reachable for its own tests.
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->alias('tests.tmonier_b2b_mcp.cart.core_adder', 'tmonier_b2b_mcp.cart.core_adder')
        ->public();
};
