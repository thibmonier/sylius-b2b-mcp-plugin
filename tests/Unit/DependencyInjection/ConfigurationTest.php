<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Tmonier\SyliusB2bMcpPlugin\DependencyInjection\Configuration;

final class ConfigurationTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(ConfigurationInterface::class, new Configuration());
    }

    public function testDefaultValues(): void
    {
        /** @var array<string, mixed> $config */
        $config = (new Processor())->processConfiguration(new Configuration(), []);

        self::assertNull($config['endpoint_path']);
        self::assertSame(Configuration::DEFAULT_HIDDEN_OFFICIAL_TOOLS, $config['hidden_official_tools']);
        self::assertIsArray($config['oauth']);
        self::assertIsArray($config['rate_limit']);
        self::assertIsArray($config['approvals']);

        /** @var array<string, mixed> $oauth */
        $oauth = $config['oauth'];
        self::assertTrue($oauth['dynamic_client_registration']);
        self::assertSame(Configuration::DEFAULT_REDIRECT_URIS, $oauth['redirect_uris']);
        self::assertFalse($oauth['allow_loopback_redirect_uris']);

        /** @var array<string, mixed> $rateLimit */
        $rateLimit = $config['rate_limit'];
        self::assertSame(120, $rateLimit['mcp_requests_per_minute']);
        self::assertSame(60, $rateLimit['oauth_requests_per_minute']);

        /** @var array<string, mixed> $approvals */
        $approvals = $config['approvals'];
        self::assertFalse($approvals['allow_decisions']);

        self::assertTrue($config['audit_log_bridge']);
        self::assertSame(50, $config['max_page_size']);
        self::assertSame(24, $config['idempotency_ttl_hours']);
        self::assertSame(90, $config['audit_retention_days']);
    }

    public function testCustomValues(): void
    {
        /** @var array<string, mixed> $config */
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'endpoint_path' => '/_custom',
            'hidden_official_tools' => ['create_order'],
            'oauth' => [
                'access_token_ttl' => 'PT30M',
                'dynamic_client_registration' => false,
            ],
            'approvals' => ['allow_decisions' => true],
            'max_page_size' => 100,
        ]]);

        self::assertSame('/_custom', $config['endpoint_path']);
        self::assertSame(['create_order'], $config['hidden_official_tools']);

        /** @var array<string, mixed> $oauth */
        $oauth = $config['oauth'];
        self::assertSame('PT30M', $oauth['access_token_ttl']);
        self::assertFalse($oauth['dynamic_client_registration']);

        /** @var array<string, mixed> $approvals */
        $approvals = $config['approvals'];
        self::assertTrue($approvals['allow_decisions']);

        self::assertSame(100, $config['max_page_size']);
    }
}
