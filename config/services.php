<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use League\OAuth2\Server\AuthorizationServer;
use Tmonier\SyliusB2bMcpPlugin\Audit\NullAuditMirror;
use Tmonier\SyliusB2bMcpPlugin\Account\AccountUsabilityChain;
use Tmonier\SyliusB2bMcpPlugin\Audit\ToolCallLogger;
use Tmonier\SyliusB2bMcpPlugin\Cart\CartLinesAdderInterface;
use Tmonier\SyliusB2bMcpPlugin\Cart\CartLockChecker;
use Tmonier\SyliusB2bMcpPlugin\Cart\CoreCartLinesAdder;
use Tmonier\SyliusB2bMcpPlugin\Cart\CustomerCartProvider;
use Tmonier\SyliusB2bMcpPlugin\Command\CreateClientCommand;
use Tmonier\SyliusB2bMcpPlugin\Command\PurgeCommand;
use Tmonier\SyliusB2bMcpPlugin\Controller\Admin\ConnectionController as AdminConnectionController;
use Tmonier\SyliusB2bMcpPlugin\Controller\OAuth\AuthorizeController;
use Tmonier\SyliusB2bMcpPlugin\Controller\OAuth\MetadataController;
use Tmonier\SyliusB2bMcpPlugin\Controller\OAuth\RegistrationController;
use Tmonier\SyliusB2bMcpPlugin\Controller\OAuth\RevocationController;
use Tmonier\SyliusB2bMcpPlugin\Controller\OAuth\TokenController;
use Tmonier\SyliusB2bMcpPlugin\Controller\Shop\ConnectionController;
use Tmonier\SyliusB2bMcpPlugin\Controller\Shop\ConsentController;
use Tmonier\SyliusB2bMcpPlugin\Idempotency\IdempotencyStore;
use Tmonier\SyliusB2bMcpPlugin\Mcp\B2bToolLoader;
use Tmonier\SyliusB2bMcpPlugin\Mcp\HiddenToolsRegistry;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\GetCheckoutLinkTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\GetOrderTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\ListMyOrdersTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\ReorderTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\ToolRunner;
use Tmonier\SyliusB2bMcpPlugin\Menu\AccountMenuListener;
use Tmonier\SyliusB2bMcpPlugin\Menu\AdminMenuListener;
use Tmonier\SyliusB2bMcpPlugin\OAuth\ApproveScopeCacheWarmer;
use Tmonier\SyliusB2bMcpPlugin\OAuth\ApproveScopeGuard;
use Tmonier\SyliusB2bMcpPlugin\OAuth\AuthorizationRequestValidator;
use Tmonier\SyliusB2bMcpPlugin\OAuth\AuthorizationServerFactory;
use Tmonier\SyliusB2bMcpPlugin\OAuth\ClientRegistrar;
use Tmonier\SyliusB2bMcpPlugin\OAuth\ConnectionManager;
use Tmonier\SyliusB2bMcpPlugin\OAuth\McpEndpoint;
use Tmonier\SyliusB2bMcpPlugin\OAuth\PsrBridge;
use Tmonier\SyliusB2bMcpPlugin\OAuth\RedirectUriPolicy;
use Tmonier\SyliusB2bMcpPlugin\OAuth\RefreshTokenDecoder;
use Tmonier\SyliusB2bMcpPlugin\OAuth\ScopeCatalog;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Repository\AccessTokenRepository;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Repository\AuthCodeRepository;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Repository\ClientRepository;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Repository\RefreshTokenRepository;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Repository\ScopeRepository;
use Tmonier\SyliusB2bMcpPlugin\OAuth\TokenStore;
use Tmonier\SyliusB2bMcpPlugin\Order\CompanyOrdersInterface;
use Tmonier\SyliusB2bMcpPlugin\Order\NullCompanyOrders;
use Tmonier\SyliusB2bMcpPlugin\Order\NullOrderApprovalStatus;
use Tmonier\SyliusB2bMcpPlugin\Order\OrderFinder;
use Tmonier\SyliusB2bMcpPlugin\Order\OrderPresenter;
use Tmonier\SyliusB2bMcpPlugin\Order\ShopUrlGenerator;
use Tmonier\SyliusB2bMcpPlugin\RateLimit\RateLimiter;
use Tmonier\SyliusB2bMcpPlugin\Security\CurrentConnection;
use Tmonier\SyliusB2bMcpPlugin\Security\McpAuthenticationListener;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // OAuth 2.1 authorization server (league/oauth2-server, opaque tokens hashed in the database)

    $services->set('tmonier_b2b_mcp.endpoint', McpEndpoint::class)->args([param('tmonier_b2b_mcp.endpoint_path')]);
    $services->alias(McpEndpoint::class, 'tmonier_b2b_mcp.endpoint');

    $services->set('tmonier_b2b_mcp.oauth.token_store', TokenStore::class)
        ->args([service('doctrine.orm.entity_manager'), service('clock'), param('tmonier_b2b_mcp.model.connection.class')]);
    $services->alias(TokenStore::class, 'tmonier_b2b_mcp.oauth.token_store');

    $services->set('tmonier_b2b_mcp.oauth.scope_catalog', ScopeCatalog::class)->args([param('tmonier_b2b_mcp.approvals.decisions_enabled')]);

    $services->set('tmonier_b2b_mcp.oauth.client_repository', ClientRepository::class)->args([service('tmonier_b2b_mcp.oauth.token_store')]);
    $services->set('tmonier_b2b_mcp.oauth.access_token_repository', AccessTokenRepository::class)->args([service('tmonier_b2b_mcp.oauth.token_store')]);
    $services->set('tmonier_b2b_mcp.oauth.refresh_token_repository', RefreshTokenRepository::class)->args([service('tmonier_b2b_mcp.oauth.token_store')]);
    $services->set('tmonier_b2b_mcp.oauth.auth_code_repository', AuthCodeRepository::class)->args([service('tmonier_b2b_mcp.oauth.token_store')]);
    $services->set('tmonier_b2b_mcp.oauth.approve_scope_guard', ApproveScopeGuard::class)
        ->args([service('doctrine.orm.entity_manager'), service('tmonier_b2b_mcp.oauth.scope_catalog'), service('cache.app'), param('tmonier_b2b_mcp.model.connection.class')]);

    $services->set('tmonier_b2b_mcp.oauth.approve_scope_cache_warmer', ApproveScopeCacheWarmer::class)
        ->args([service('tmonier_b2b_mcp.oauth.approve_scope_guard'), service('logger')])
        ->tag('kernel.cache_warmer');

    $services->set('tmonier_b2b_mcp.oauth.scope_repository', ScopeRepository::class)->args([service('tmonier_b2b_mcp.oauth.token_store'), service('tmonier_b2b_mcp.oauth.scope_catalog'), service('tmonier_b2b_mcp.oauth.approve_scope_guard')]);

    $services->set('tmonier_b2b_mcp.oauth.authorization_server', AuthorizationServer::class)
        ->factory([AuthorizationServerFactory::class, 'create'])
        ->args([
            service('tmonier_b2b_mcp.oauth.client_repository'),
            service('tmonier_b2b_mcp.oauth.access_token_repository'),
            service('tmonier_b2b_mcp.oauth.scope_repository'),
            service('tmonier_b2b_mcp.oauth.auth_code_repository'),
            service('tmonier_b2b_mcp.oauth.refresh_token_repository'),
            param('tmonier_b2b_mcp.oauth.encryption_key'),
            param('tmonier_b2b_mcp.oauth.access_token_ttl'),
            param('tmonier_b2b_mcp.oauth.refresh_token_ttl'),
            param('tmonier_b2b_mcp.oauth.auth_code_ttl'),
        ]);

    $services->set('tmonier_b2b_mcp.oauth.psr_bridge', PsrBridge::class);
    $services->set('tmonier_b2b_mcp.oauth.refresh_token_decoder', RefreshTokenDecoder::class)->args([param('tmonier_b2b_mcp.oauth.encryption_key')]);

    $services->set('tmonier_b2b_mcp.oauth.redirect_uri_policy', RedirectUriPolicy::class)
        ->args([param('tmonier_b2b_mcp.oauth.redirect_uris'), param('tmonier_b2b_mcp.oauth.allow_loopback_redirect_uris')]);
    $services->alias(RedirectUriPolicy::class, 'tmonier_b2b_mcp.oauth.redirect_uri_policy');

    $services->set('tmonier_b2b_mcp.oauth.client_registrar', ClientRegistrar::class)
        ->args([service('doctrine.orm.entity_manager'), service('tmonier_b2b_mcp.oauth.redirect_uri_policy')]);

    $services->set('tmonier_b2b_mcp.oauth.connection_manager', ConnectionManager::class)
        ->args([
            service('tmonier_b2b_mcp.repository.connection'),
            service('tmonier_b2b_mcp.factory.connection'),
            service('doctrine.orm.entity_manager'),
            service('tmonier_b2b_mcp.oauth.token_store'),
            service('tmonier_b2b_mcp.audit.mirror'),
        ]);
    $services->alias(ConnectionManager::class, 'tmonier_b2b_mcp.oauth.connection_manager')->public();

    $services->set('tmonier_b2b_mcp.oauth.authorization_request_validator', AuthorizationRequestValidator::class)
        ->args([
            service('tmonier_b2b_mcp.oauth.authorization_server'),
            service('tmonier_b2b_mcp.oauth.psr_bridge'),
            service('tmonier_b2b_mcp.oauth.redirect_uri_policy'),
            service('tmonier_b2b_mcp.endpoint'),
        ]);

    $services->set('tmonier_b2b_mcp.rate_limiter', RateLimiter::class)
        ->args([
            service('limiter.tmonier_b2b_mcp_requests'),
            service('limiter.tmonier_b2b_mcp_oauth'),
            service('limiter.tmonier_b2b_mcp_registration'),
        ]);

    $services->set('tmonier_b2b_mcp.account.usability_chain', AccountUsabilityChain::class)
        ->args([tagged_iterator('tmonier_b2b_mcp.account_usability_checker'), service('logger')->nullOnInvalid()])
        ->tag('monolog.logger', ['channel' => 'mcp']);
    $services->alias(AccountUsabilityChain::class, 'tmonier_b2b_mcp.account.usability_chain');

    // Before (9) and after (7) the firewall listener (8).
    $services->set('tmonier_b2b_mcp.security.authentication_listener', McpAuthenticationListener::class)
        ->args([
            service('tmonier_b2b_mcp.oauth.token_store'),
            service('sylius.context.channel'),
            service('tmonier_b2b_mcp.endpoint'),
            service('doctrine.orm.entity_manager'),
            service('tmonier_b2b_mcp.rate_limiter'),
            service('security.token_storage'),
            service('tmonier_b2b_mcp.oauth.scope_catalog'),
            service('tmonier_b2b_mcp.oauth.approve_scope_guard'),
            service('tmonier_b2b_mcp.account.usability_chain'),
            service('tmonier_b2b_mcp.audit.tool_call_logger'),
        ])
        ->tag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'authenticate', 'priority' => 9])
        ->tag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'setSecurityToken', 'priority' => 7]);

    $services->set('tmonier_b2b_mcp.security.current_connection', CurrentConnection::class)->args([service('request_stack')]);

    // Controllers

    $controllers = $services->defaults()->public()->tag('controller.service_arguments');

    $controllers->set('tmonier_b2b_mcp.controller.oauth.metadata', MetadataController::class)
        ->args([service('tmonier_b2b_mcp.endpoint'), service('router'), param('tmonier_b2b_mcp.oauth.dynamic_client_registration'), service('tmonier_b2b_mcp.oauth.scope_catalog')]);

    $controllers->set('tmonier_b2b_mcp.controller.oauth.registration', RegistrationController::class)
        ->args([service('tmonier_b2b_mcp.oauth.client_registrar'), service('tmonier_b2b_mcp.rate_limiter'), param('tmonier_b2b_mcp.oauth.dynamic_client_registration')]);

    $controllers->set('tmonier_b2b_mcp.controller.oauth.authorize', AuthorizeController::class)
        ->args([
            service('tmonier_b2b_mcp.oauth.authorization_request_validator'),
            service('tmonier_b2b_mcp.oauth.psr_bridge'),
            service('tmonier_b2b_mcp.rate_limiter'),
            service('sylius.context.locale'),
            service('router'),
        ]);

    $controllers->set('tmonier_b2b_mcp.controller.oauth.token', TokenController::class)
        ->args([service('tmonier_b2b_mcp.oauth.authorization_server'), service('tmonier_b2b_mcp.oauth.psr_bridge'), service('tmonier_b2b_mcp.rate_limiter')]);

    $controllers->set('tmonier_b2b_mcp.controller.oauth.revocation', RevocationController::class)
        ->args([
            service('tmonier_b2b_mcp.oauth.client_repository'),
            service('tmonier_b2b_mcp.oauth.token_store'),
            service('tmonier_b2b_mcp.rate_limiter'),
            service('tmonier_b2b_mcp.oauth.refresh_token_decoder'),
        ]);

    $controllers->set('tmonier_b2b_mcp.controller.shop.consent', ConsentController::class)
        ->args([
            service('tmonier_b2b_mcp.oauth.authorization_request_validator'),
            service('tmonier_b2b_mcp.oauth.authorization_server'),
            service('tmonier_b2b_mcp.oauth.connection_manager'),
            service('tmonier_b2b_mcp.oauth.psr_bridge'),
            service('security.helper'),
            service('sylius.context.channel'),
            service('security.csrf.token_manager'),
            service('twig'),
            service('tmonier_b2b_mcp.oauth.scope_catalog'),
            service('tmonier_b2b_mcp.account.usability_chain'),
        ]);

    $controllers->set('tmonier_b2b_mcp.controller.shop.connection', ConnectionController::class)
        ->args([
            service('tmonier_b2b_mcp.repository.connection'),
            service('tmonier_b2b_mcp.oauth.connection_manager'),
            service('security.helper'),
            service('security.csrf.token_manager'),
            service('router'),
            service('twig'),
        ]);

    $controllers->set('tmonier_b2b_mcp.controller.admin.connection', AdminConnectionController::class)
        ->args([
            service('tmonier_b2b_mcp.repository.connection'),
            service('tmonier_b2b_mcp.oauth.connection_manager'),
            service('security.csrf.token_manager'),
            service('router'),
        ]);

    $services = $container->services();

    // MCP tools (registered on the server of sylius/mcp-server-plugin)

    $services->set('tmonier_b2b_mcp.mcp.hidden_tools_registry', HiddenToolsRegistry::class)
        ->decorate('mcp.registry')
        ->args([service('.inner'), param('tmonier_b2b_mcp.hidden_official_tools')]);

    $services->set('tmonier_b2b_mcp.mcp.tool_loader', B2bToolLoader::class)
        ->args([tagged_iterator('tmonier_b2b_mcp.tool')])
        ->tag('mcp.loader');

    $services->set('tmonier_b2b_mcp.idempotency_store', IdempotencyStore::class)
        ->args([service('doctrine.dbal.default_connection'), service('clock'), param('tmonier_b2b_mcp.idempotency_ttl_hours')]);

    $services->set('tmonier_b2b_mcp.audit.mirror', NullAuditMirror::class);

    $services->set('tmonier_b2b_mcp.audit.tool_call_logger', ToolCallLogger::class)
        ->args([service('doctrine.orm.entity_manager'), service('logger'), service('tmonier_b2b_mcp.audit.mirror')])
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set('tmonier_b2b_mcp.mcp.tool_runner', ToolRunner::class)
        ->args([
            service('tmonier_b2b_mcp.security.current_connection'),
            service('tmonier_b2b_mcp.idempotency_store'),
            service('tmonier_b2b_mcp.audit.tool_call_logger'),
        ]);
    $services->alias(ToolRunner::class, 'tmonier_b2b_mcp.mcp.tool_runner');

    $services->set('tmonier_b2b_mcp.order.company_orders', NullCompanyOrders::class);
    $services->alias(CompanyOrdersInterface::class, 'tmonier_b2b_mcp.order.company_orders');

    $services->set('tmonier_b2b_mcp.order.finder', OrderFinder::class)
        ->args([service('sylius.repository.order'), service('tmonier_b2b_mcp.order.company_orders')]);
    $services->alias(OrderFinder::class, 'tmonier_b2b_mcp.order.finder');

    $services->set('tmonier_b2b_mcp.order.presenter', OrderPresenter::class);

    $services->set('tmonier_b2b_mcp.order.approval_status', NullOrderApprovalStatus::class);
    $services->alias(OrderPresenter::class, 'tmonier_b2b_mcp.order.presenter');

    $services->set('tmonier_b2b_mcp.order.shop_url_generator', ShopUrlGenerator::class)->args([service('router')]);
    $services->alias(ShopUrlGenerator::class, 'tmonier_b2b_mcp.order.shop_url_generator');

    $services->set('tmonier_b2b_mcp.cart.provider', CustomerCartProvider::class)
        ->args([service('sylius.repository.order'), service('sylius.factory.order')]);
    $services->alias(CustomerCartProvider::class, 'tmonier_b2b_mcp.cart.provider');

    $services->set('tmonier_b2b_mcp.cart.lock_checker', CartLockChecker::class)
        ->args([service('tmonier_quote_request.quote_cart_checker')->nullOnInvalid()]);

    $services->set('tmonier_b2b_mcp.cart.core_adder', CoreCartLinesAdder::class)
        ->args([
            service('sylius.repository.product_variant'),
            service('sylius.checker.inventory.availability'),
            service('sylius.factory.cart_item'),
            service('sylius.modifier.order_item_quantity'),
            service('sylius.order_processing.order_processor'),
            service('sylius.manager.order'),
            service('tmonier_b2b_mcp.cart.lock_checker'),
            param('sylius.order_item_quantity_modifier.limit'),
        ]);
    $services->alias('tmonier_b2b_mcp.cart.adder', 'tmonier_b2b_mcp.cart.core_adder');
    $services->alias(CartLinesAdderInterface::class, 'tmonier_b2b_mcp.cart.adder');

    $tools = $services->defaults()->tag('mcp.tool')->tag('tmonier_b2b_mcp.tool');

    $tools->set(ListMyOrdersTool::class)
        ->args([
            service('tmonier_b2b_mcp.mcp.tool_runner'),
            service('tmonier_b2b_mcp.order.finder'),
            service('tmonier_b2b_mcp.order.presenter'),
            param('tmonier_b2b_mcp.max_page_size'),
        ]);

    $tools->set(GetOrderTool::class)
        ->args([service('tmonier_b2b_mcp.mcp.tool_runner'), service('tmonier_b2b_mcp.order.finder'), service('tmonier_b2b_mcp.order.presenter'), service('tmonier_b2b_mcp.order.approval_status')]);

    $tools->set(ReorderTool::class)
        ->args([
            service('tmonier_b2b_mcp.mcp.tool_runner'),
            service('tmonier_b2b_mcp.order.finder'),
            service('tmonier_b2b_mcp.cart.provider'),
            service('tmonier_b2b_mcp.cart.adder'),
            service('tmonier_b2b_mcp.order.presenter'),
            service('tmonier_b2b_mcp.order.shop_url_generator'),
        ]);

    $tools->set(GetCheckoutLinkTool::class)
        ->args([
            service('tmonier_b2b_mcp.mcp.tool_runner'),
            service('tmonier_b2b_mcp.cart.provider'),
            service('tmonier_b2b_mcp.order.presenter'),
            service('tmonier_b2b_mcp.order.shop_url_generator'),
        ]);

    $services = $container->services();

    // Console, menus

    $services->set('tmonier_b2b_mcp.command.purge', PurgeCommand::class)
        ->args([
            service('tmonier_b2b_mcp.oauth.token_store'),
            service('tmonier_b2b_mcp.idempotency_store'),
            service('tmonier_b2b_mcp.repository.tool_call'),
            service('clock'),
            param('tmonier_b2b_mcp.audit_retention_days'),
            service('tmonier_b2b_mcp.oauth.approve_scope_guard'),
        ])
        ->tag('console.command');

    $services->set('tmonier_b2b_mcp.command.create_client', CreateClientCommand::class)
        ->args([service('tmonier_b2b_mcp.oauth.client_registrar'), service('tmonier_b2b_mcp.oauth.redirect_uri_policy')])
        ->tag('console.command');

    $services->set('tmonier_b2b_mcp.listener.account_menu', AccountMenuListener::class)
        ->tag('kernel.event_listener', ['event' => 'sylius.menu.shop.account']);

    $services->set('tmonier_b2b_mcp.listener.admin_menu', AdminMenuListener::class)
        ->tag('kernel.event_listener', ['event' => 'sylius.menu.admin.main']);
};
