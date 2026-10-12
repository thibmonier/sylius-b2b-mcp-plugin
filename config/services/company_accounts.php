<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tmonier\SyliusB2bMcpPlugin\Order\CompanyAccountsOrders;

/** Loaded when tmonier/sylius-company-accounts-plugin is enabled: company orders, decided by its voters. */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('tmonier_b2b_mcp.order.company_orders', CompanyAccountsOrders::class)
        ->args([
            service('tmonier_company_accounts.current_member_resolver'),
            service('tmonier_company_accounts.repository.company_order'),
            service('security.authorization_checker'),
        ]);
};
