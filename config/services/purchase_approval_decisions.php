<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\DecideApprovalTool;

/** Loaded when approvals.allow_decisions is on and the purchase approval plugin is enabled (opt-in). */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set(DecideApprovalTool::class)
        ->args([
            service('tmonier_b2b_mcp.mcp.tool_runner'),
            service('tmonier_company_accounts.current_member_resolver'),
            service('tmonier_purchase_approval.repository.order_approval'),
            service('tmonier_purchase_approval.decision_maker'),
            service('security.authorization_checker'),
            service('translator'),
        ])
        ->tag('mcp.tool')
        ->tag('tmonier_b2b_mcp.tool');
};
