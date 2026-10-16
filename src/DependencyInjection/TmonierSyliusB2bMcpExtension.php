<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Tmonier\SyliusB2bMcpPlugin\Account\AccountUsabilityCheckerInterface;

final class TmonierSyliusB2bMcpExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public const DEFAULT_ENDPOINT_PATH = '/_mcp';

    public const TAG_ACCOUNT_USABILITY_CHECKER = 'tmonier_b2b_mcp.account_usability_checker';

    /** Optional tools: bundle class => services file. */
    public const OPTIONAL_TOOLS = [
        \Tmonier\SyliusQuickOrderPlugin\TmonierSyliusQuickOrderPlugin::class => 'services/quick_order.php',
        \Tmonier\SyliusQuoteRequestPlugin\TmonierSyliusQuoteRequestPlugin::class => 'services/quote_request.php',
        \Tmonier\SyliusTradeCreditPlugin\TmonierSyliusTradeCreditPlugin::class => 'services/trade_credit.php',
        \Tmonier\SyliusCompanyAccountsPlugin\TmonierSyliusCompanyAccountsPlugin::class => 'services/company_accounts.php',
        self::PURCHASE_APPROVAL_BUNDLE => 'services/purchase_approval.php',
    ];

    public const PURCHASE_APPROVAL_BUNDLE = \Tmonier\SyliusPurchaseApprovalPlugin\TmonierSyliusPurchaseApprovalPlugin::class;

    public const AUDIT_LOG_BUNDLE = \Tmonier\SyliusAuditLogPlugin\TmonierSyliusAuditLogPlugin::class;

    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{endpoint_path: string|null, hidden_official_tools: list<string>, oauth: array{encryption_key: string, access_token_ttl: string, refresh_token_ttl: string, auth_code_ttl: string, dynamic_client_registration: bool, redirect_uris: list<string>, allow_loopback_redirect_uris: bool}, approvals: array{allow_decisions: bool}, audit_log_bridge: bool, max_page_size: int, idempotency_ttl_hours: int, audit_retention_days: int, driver: string, resources: array<string, mixed>} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $this->registerResources('tmonier_b2b_mcp', $config['driver'], $config['resources'], $container);

        // Set at prepend time, when the configuration of symfony/mcp-bundle is readable.
        if (null !== $config['endpoint_path'] || !$container->hasParameter('tmonier_b2b_mcp.endpoint_path')) {
            $container->setParameter('tmonier_b2b_mcp.endpoint_path', $this->endpointPath($container, $config['endpoint_path']));
        }
        $container->setParameter('tmonier_b2b_mcp.hidden_official_tools', $config['hidden_official_tools']);
        foreach ($config['oauth'] as $key => $value) {
            $container->setParameter('tmonier_b2b_mcp.oauth.' . $key, $value);
        }
        $container->setParameter('tmonier_b2b_mcp.max_page_size', $config['max_page_size']);
        $container->setParameter('tmonier_b2b_mcp.idempotency_ttl_hours', $config['idempotency_ttl_hours']);
        $container->setParameter('tmonier_b2b_mcp.audit_retention_days', $config['audit_retention_days']);

        /** @var array<string, class-string> $bundles */
        $bundles = $container->getParameter('kernel.bundles');
        $decisions = $config['approvals']['allow_decisions'] && \in_array(self::PURCHASE_APPROVAL_BUNDLE, $bundles, true);
        $container->setParameter('tmonier_b2b_mcp.approvals.decisions_enabled', $decisions);

        $container->registerForAutoconfiguration(AccountUsabilityCheckerInterface::class)->addTag(self::TAG_ACCOUNT_USABILITY_CHECKER);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.php');

        $enabled = [];
        foreach (self::OPTIONAL_TOOLS as $bundle => $file) {
            if (\in_array($bundle, $bundles, true)) {
                $loader->load($file);
                $enabled[] = $bundle;
            }
        }
        if ($decisions) {
            $loader->load('services/purchase_approval_decisions.php');
        }
        if ($config['audit_log_bridge'] && \in_array(self::AUDIT_LOG_BUNDLE, $bundles, true)) {
            $loader->load('services/audit_log.php');
            $enabled[] = self::AUDIT_LOG_BUNDLE;
        }
        $container->setParameter('tmonier_b2b_mcp.enabled_integrations', $enabled);
    }

    public function prepend(ContainerBuilder $container): void
    {
        // Grids are processed before this extension is loaded: the model class parameters have
        // to exist at prepend time.
        $configs = $container->getExtensionConfig($this->getAlias());
        /** @var array{endpoint_path: string|null, rate_limit: array{mcp_requests_per_minute: int, oauth_requests_per_minute: int, client_registrations_per_hour: int, cache_pool: string}, resources: array<string, array{classes: array{model: string}}>} $config */
        $config = $this->processConfiguration(new Configuration(), array_map(
            static fn (array $config): array => array_intersect_key($config, array_flip(['endpoint_path', 'rate_limit', 'resources'])),
            $configs,
        ));
        foreach ($config['resources'] as $name => $resource) {
            $container->setParameter(\sprintf('tmonier_b2b_mcp.model.%s.class', $name), $resource['classes']['model']);
        }

        $container->setParameter('tmonier_b2b_mcp.endpoint_path', $this->endpointPath($container, $config['endpoint_path']));

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'TmonierSyliusB2bMcpPlugin' => [
                        'type' => 'xml',
                        'dir' => 'config/doctrine',
                        'prefix' => 'Tmonier\SyliusB2bMcpPlugin\Entity',
                        'is_bundle' => true,
                    ],
                ],
            ],
        ]);

        $limiter = static fn (int $limit, string $interval): array => [
            'policy' => 'sliding_window',
            'limit' => $limit,
            'interval' => $interval,
            'cache_pool' => $config['rate_limit']['cache_pool'],
            // Without a lock, parallel requests read the same count and all get through.
            'lock_factory' => 'lock.factory',
        ];
        $container->prependExtensionConfig('framework', ['lock' => ['enabled' => true, 'resources' => ['default' => ['flock']]]]);
        $container->prependExtensionConfig('framework', ['rate_limiter' => [
            'tmonier_b2b_mcp_requests' => $limiter($config['rate_limit']['mcp_requests_per_minute'], '1 minute'),
            'tmonier_b2b_mcp_oauth' => $limiter($config['rate_limit']['oauth_requests_per_minute'], '1 minute'),
            'tmonier_b2b_mcp_registration' => $limiter($config['rate_limit']['client_registrations_per_hour'], '1 hour'),
        ]]);

        $this->prependDoctrineMigrations($container);
    }

    public function getAlias(): string
    {
        return 'tmonier_sylius_b2b_mcp';
    }

    protected function getMigrationsNamespace(): string
    {
        return 'Tmonier\SyliusB2bMcpPlugin\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@TmonierSyliusB2bMcpPlugin/src/Migrations';
    }

    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }

    /** The configured path, else the HTTP path of symfony/mcp-bundle, else its default. */
    private function endpointPath(ContainerBuilder $container, ?string $configured): string
    {
        if (null !== $configured && '' !== $configured) {
            return '/' . ltrim($configured, '/');
        }

        $path = self::DEFAULT_ENDPOINT_PATH;
        foreach ($container->getExtensionConfig('mcp') as $mcpConfig) {
            $http = $mcpConfig['http'] ?? null;
            $candidate = \is_array($http) ? ($http['path'] ?? null) : null;
            if (\is_string($candidate) && '' !== $candidate && !str_contains($candidate, '%')) {
                $path = '/' . ltrim($candidate, '/');
            }
        }

        return $path;
    }
}
