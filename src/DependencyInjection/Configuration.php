<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\DependencyInjection;

use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Sylius\Resource\Factory\Factory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\Connection;
use Tmonier\SyliusB2bMcpPlugin\Entity\ConnectionInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\ToolCall;
use Tmonier\SyliusB2bMcpPlugin\Entity\ToolCallInterface;
use Tmonier\SyliusB2bMcpPlugin\Repository\ConnectionRepository;
use Tmonier\SyliusB2bMcpPlugin\Repository\ToolCallRepository;

final class Configuration implements ConfigurationInterface
{
    /** Guest cart and checkout tools of sylius/mcp-server-plugin: hidden by default (handoff to the shop checkout). */
    public const DEFAULT_HIDDEN_OFFICIAL_TOOLS = [
        'create_order',
        'add_item_to_order',
        'fetch_order',
        'update_order_address',
        'list_shipping_methods',
        'select_shipping_method',
        'list_payment_methods',
        'select_payment_method',
        'complete_checkout',
    ];

    /** Redirect URIs of the hosted Claude surfaces and of ChatGPT (a trailing "*" matches one path segment). */
    public const DEFAULT_REDIRECT_URIS = [
        'https://claude.ai/api/mcp/auth_callback',
        'https://claude.com/api/mcp/auth_callback',
        'https://chatgpt.com/connector_platform_oauth_redirect',
        'https://chatgpt.com/connector/oauth/*',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('tmonier_sylius_b2b_mcp');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('endpoint_path')
                    ->info('Path of the MCP endpoint. Defaults to the "mcp.http.path" setting of symfony/mcp-bundle, then "/_mcp".')
                    ->defaultNull()
                ->end()
                ->arrayNode('hidden_official_tools')
                    ->info('Tools of sylius/mcp-server-plugin that are not registered. The default hides its guest cart and checkout tools; its catalog tools stay.')
                    ->scalarPrototype()->end()
                    ->defaultValue(self::DEFAULT_HIDDEN_OFFICIAL_TOOLS)
                ->end()
                ->arrayNode('oauth')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('encryption_key')
                            ->info('Encrypts authorization codes and refresh tokens. Defaults to the kernel secret; changing it invalidates them.')
                            ->defaultValue('%kernel.secret%')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('access_token_ttl')->defaultValue('PT1H')->cannotBeEmpty()->end()
                        ->scalarNode('refresh_token_ttl')->defaultValue('P30D')->cannotBeEmpty()->end()
                        ->scalarNode('auth_code_ttl')->defaultValue('PT10M')->cannotBeEmpty()->end()
                        ->booleanNode('dynamic_client_registration')
                            ->info('RFC 7591 registration endpoint, used by Claude and ChatGPT. Registered redirect URIs must match "redirect_uris".')
                            ->defaultTrue()
                        ->end()
                        ->arrayNode('redirect_uris')
                            ->info('Allowed redirect URIs of the assistants (exact, or ending with "*" for one path segment).')
                            ->scalarPrototype()->end()
                            ->defaultValue(self::DEFAULT_REDIRECT_URIS)
                        ->end()
                        ->booleanNode('allow_loopback_redirect_uris')
                            ->info('Also allow http://localhost and http://127.0.0.1 redirect URIs on any port (Claude Code, MCP Inspector).')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('rate_limit')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('mcp_requests_per_minute')->info('Per connection (customer and assistant).')->defaultValue(120)->min(1)->end()
                        ->integerNode('oauth_requests_per_minute')->info('Token and revocation endpoints: per client; authorization endpoint: per IP address.')->defaultValue(60)->min(1)->end()
                        ->integerNode('client_registrations_per_hour')->info('Per IP address. Claude and ChatGPT register one client per customer from a few server addresses: keep it generous.')->defaultValue(60)->min(1)->end()
                        ->scalarNode('cache_pool')->defaultValue('cache.rate_limiter')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->arrayNode('approvals')
                    ->info('With tmonier/sylius-purchase-approval-plugin.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('allow_decisions')
                            ->info('Register decide_approval and the b2b:approve scope: approvers may approve or reject purchase orders from the assistant. Off by default (see README, risks).')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('audit_log_bridge')
                    ->info('With tmonier/sylius-audit-log-plugin: mirror the tool calls, consents and revocations into its log (the own audit table stays the reference).')
                    ->defaultTrue()
                ->end()
                ->integerNode('max_page_size')->defaultValue(50)->min(1)->max(200)->end()
                ->integerNode('idempotency_ttl_hours')->defaultValue(24)->min(1)->end()
                ->integerNode('audit_retention_days')->defaultValue(90)->min(1)->end()
                ->scalarNode('driver')->defaultValue(SyliusResourceBundle::DRIVER_DOCTRINE_ORM)->end()
                ->arrayNode('resources')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->append($this->resource('connection', Connection::class, ConnectionInterface::class, ConnectionRepository::class))
                        ->append($this->resource('tool_call', ToolCall::class, ToolCallInterface::class, ToolCallRepository::class))
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    private function resource(string $name, string $model, string $interface, string $repository): ArrayNodeDefinition
    {
        $node = (new TreeBuilder($name))->getRootNode();
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('classes')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('model')->defaultValue($model)->cannotBeEmpty()->end()
                        ->scalarNode('interface')->defaultValue($interface)->cannotBeEmpty()->end()
                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                        ->scalarNode('repository')->defaultValue($repository)->cannotBeEmpty()->end()
                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }
}
