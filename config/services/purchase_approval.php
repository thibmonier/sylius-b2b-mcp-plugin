<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tmonier\SyliusB2bMcpPlugin\Approval\PurchaseApprovalStatus;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\ListPendingApprovalsTool;

/** Loaded when tmonier/sylius-purchase-approval-plugin is enabled: list_pending_approvals, approval in get_order. */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('tmonier_b2b_mcp.order.approval_status', PurchaseApprovalStatus::class)
        ->args([service('tmonier_purchase_approval.repository.order_approval')]);

    $services->set(ListPendingApprovalsTool::class)
        ->args([
            service('tmonier_b2b_mcp.mcp.tool_runner'),
            service('tmonier_company_accounts.current_member_resolver'),
            service('tmonier_purchase_approval.repository.order_approval'),
            service('tmonier_purchase_approval.policy'),
            service('security.authorization_checker'),
            service('clock'),
        ])
        ->tag('mcp.tool')
        ->tag('tmonier_b2b_mcp.tool');
};
