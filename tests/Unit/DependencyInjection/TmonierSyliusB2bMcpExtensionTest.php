<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tmonier\SyliusB2bMcpPlugin\Approval\PurchaseApprovalStatus;
use Tmonier\SyliusB2bMcpPlugin\Audit\AuditLogMirror;
use Tmonier\SyliusB2bMcpPlugin\Audit\NullAuditMirror;
use Tmonier\SyliusB2bMcpPlugin\Cart\QuickOrderCartLinesAdder;
use Tmonier\SyliusB2bMcpPlugin\DependencyInjection\TmonierSyliusB2bMcpExtension;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\DecideApprovalTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\GetCheckoutLinkTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\GetOrderTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\GetTradeCreditStatementTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\ListMyOrdersTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\ListMyQuotesTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\ListPendingApprovalsTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\QuickOrderAddTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\ReorderTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\RequestQuoteTool;
use Tmonier\SyliusB2bMcpPlugin\Order\CompanyAccountsOrders;
use Tmonier\SyliusB2bMcpPlugin\Order\NullCompanyOrders;

final class TmonierSyliusB2bMcpExtensionTest extends TestCase
{
    public function testWithoutTheOtherPluginsOnlyTheCoreToolsAreRegistered(): void
    {
        $container = $this->load([]);

        self::assertSame([ListMyOrdersTool::class, GetOrderTool::class, ReorderTool::class, GetCheckoutLinkTool::class], $this->tools($container));
        self::assertSame(NullCompanyOrders::class, $container->getDefinition('tmonier_b2b_mcp.order.company_orders')->getClass());
        self::assertSame('tmonier_b2b_mcp.cart.core_adder', (string) $container->getAlias('tmonier_b2b_mcp.cart.adder'));
    }

    public function testEachPluginEnablesItsTools(): void
    {
        $container = $this->load(array_keys(TmonierSyliusB2bMcpExtension::OPTIONAL_TOOLS));

        self::assertSame([
            ListMyOrdersTool::class, GetOrderTool::class, ReorderTool::class, GetCheckoutLinkTool::class,
            QuickOrderAddTool::class, RequestQuoteTool::class, ListMyQuotesTool::class, GetTradeCreditStatementTool::class,
            ListPendingApprovalsTool::class,
        ], $this->tools($container));
        self::assertFalse($container->getParameter('tmonier_b2b_mcp.approvals.decisions_enabled'), 'Approval decisions are opt-in.');
        self::assertSame(PurchaseApprovalStatus::class, $container->getDefinition('tmonier_b2b_mcp.order.approval_status')->getClass());
        self::assertSame(NullAuditMirror::class, $container->getDefinition('tmonier_b2b_mcp.audit.mirror')->getClass(), 'No audit log plugin here.');
        self::assertSame(CompanyAccountsOrders::class, $container->getDefinition('tmonier_b2b_mcp.order.company_orders')->getClass());
        // Reorder follows the quick order rules when that plugin is installed.
        self::assertSame(QuickOrderCartLinesAdder::class, $container->findDefinition('tmonier_b2b_mcp.cart.adder')->getClass());
    }

    public function testApprovalDecisionsAreOptIn(): void
    {
        $container = $this->load([TmonierSyliusB2bMcpExtension::PURCHASE_APPROVAL_BUNDLE], ['approvals' => ['allow_decisions' => true]]);
        self::assertContains(DecideApprovalTool::class, $this->tools($container));
        self::assertTrue($container->getParameter('tmonier_b2b_mcp.approvals.decisions_enabled'));

        // Without the purchase approval plugin the option does nothing.
        $container = $this->load([], ['approvals' => ['allow_decisions' => true]]);
        self::assertNotContains(DecideApprovalTool::class, $this->tools($container));
        self::assertFalse($container->getParameter('tmonier_b2b_mcp.approvals.decisions_enabled'));
    }

    public function testTheAuditLogBridgeFollowsItsPluginAndItsSwitch(): void
    {
        self::assertSame(AuditLogMirror::class, $this->load([TmonierSyliusB2bMcpExtension::AUDIT_LOG_BUNDLE])->getDefinition('tmonier_b2b_mcp.audit.mirror')->getClass());
        self::assertSame(NullAuditMirror::class, $this->load([TmonierSyliusB2bMcpExtension::AUDIT_LOG_BUNDLE], ['audit_log_bridge' => false])->getDefinition('tmonier_b2b_mcp.audit.mirror')->getClass());
    }

    public function testTheTradeCreditToolAloneIsRegisteredWithItsPlugin(): void
    {
        $container = $this->load([\Tmonier\SyliusTradeCreditPlugin\TmonierSyliusTradeCreditPlugin::class]);

        self::assertContains(GetTradeCreditStatementTool::class, $this->tools($container));
        self::assertNotContains(QuickOrderAddTool::class, $this->tools($container));
        self::assertNotContains(RequestQuoteTool::class, $this->tools($container));
    }

    public function testTheEndpointPathFollowsTheMcpBundle(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);
        $container->registerExtension(new TmonierSyliusB2bMcpExtension());
        $container->prependExtensionConfig('mcp', ['http' => ['path' => '/mcp/shop']]);
        (new TmonierSyliusB2bMcpExtension())->prepend($container);

        self::assertSame('/mcp/shop', $container->getParameter('tmonier_b2b_mcp.endpoint_path'));
    }

    /**
     * @param list<string> $bundles
     * @param array<string, mixed> $config
     */
    private function load(array $bundles, array $config = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', array_combine(array_map(static fn (string $class): string => substr((string) strrchr($class, '\\'), 1), $bundles), $bundles));
        (new TmonierSyliusB2bMcpExtension())->load([$config], $container);

        return $container;
    }

    /** @return list<string> */
    private function tools(ContainerBuilder $container): array
    {
        $tools = array_keys($container->findTaggedServiceIds('tmonier_b2b_mcp.tool'));
        self::assertSame($tools, array_values(array_intersect(array_keys($container->findTaggedServiceIds('mcp.tool')), $tools)));

        return $tools;
    }
}
