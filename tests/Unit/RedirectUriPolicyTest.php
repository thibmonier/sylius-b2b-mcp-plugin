<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\DependencyInjection\Configuration;
use Tmonier\SyliusB2bMcpPlugin\OAuth\RedirectUriPolicy;

final class RedirectUriPolicyTest extends TestCase
{
    /** @return iterable<string, array{string, bool}> */
    public static function uris(): iterable
    {
        yield 'Claude' => ['https://claude.ai/api/mcp/auth_callback', true];
        yield 'Claude (claude.com)' => ['https://claude.com/api/mcp/auth_callback', true];
        yield 'ChatGPT, legacy' => ['https://chatgpt.com/connector_platform_oauth_redirect', true];
        yield 'ChatGPT, per app' => ['https://chatgpt.com/connector/oauth/AbC_12-3', true];
        yield 'ChatGPT, two segments' => ['https://chatgpt.com/connector/oauth/abc/def', false];
        yield 'ChatGPT, query' => ['https://chatgpt.com/connector/oauth/abc?x=1', false];
        yield 'ChatGPT, no segment' => ['https://chatgpt.com/connector/oauth/', false];
        yield 'suffix' => ['https://claude.ai/api/mcp/auth_callback/../evil', false];
        yield 'other host' => ['https://claude.ai.evil.example.com/api/mcp/auth_callback', false];
        yield 'http' => ['http://claude.ai/api/mcp/auth_callback', false];
        yield 'fragment' => ['https://claude.ai/api/mcp/auth_callback#x', false];
        yield 'control character' => ["https://claude.ai/api/mcp/auth_callback\n", false];
        yield 'loopback (disabled)' => ['http://localhost:6274/oauth/callback', false];
    }

    #[DataProvider('uris')]
    public function testTheDefaultPolicyAllowsTheAssistantsOnly(string $uri, bool $allowed): void
    {
        self::assertSame($allowed, (new RedirectUriPolicy(Configuration::DEFAULT_REDIRECT_URIS, false))->isAllowed($uri));
    }

    public function testLoopbackRedirectsCanBeAllowedForLocalClients(): void
    {
        $policy = new RedirectUriPolicy([], true);

        self::assertTrue($policy->isAllowed('http://localhost:6274/oauth/callback'));
        self::assertTrue($policy->isAllowed('http://127.0.0.1:53682/callback'));
        self::assertFalse($policy->isAllowed('http://localhost.evil.example.com/callback'));
        self::assertFalse($policy->isAllowed('http://192.168.1.10:8080/callback'));
        self::assertFalse($policy->isAllowed('https://claude.ai/api/mcp/auth_callback'));
    }
}
