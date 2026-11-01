<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\OAuth;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\OAuth\KnownAssistants;

final class KnownAssistantsTest extends TestCase
{
    #[DataProvider('knownHostsProvider')]
    public function testKnownHosts(string $host, ?string $expected): void
    {
        self::assertSame($expected, KnownAssistants::nameForHost($host));
    }

    /** @return iterable<string, array{string, string|null}> */
    public static function knownHostsProvider(): iterable
    {
        yield 'claude.ai' => ['claude.ai', 'Claude'];
        yield 'claude.com' => ['claude.com', 'Claude'];
        yield 'chatgpt.com' => ['chatgpt.com', 'ChatGPT'];
        yield 'Claude.AI uppercase' => ['Claude.AI', 'Claude'];
        yield 'unknown host' => ['example.com', null];
        yield 'empty' => ['', null];
    }
}
