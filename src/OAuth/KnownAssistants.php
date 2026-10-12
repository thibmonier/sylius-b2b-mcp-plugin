<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth;

/**
 * Name of the assistant derived from the host its authorization codes go back to (verified by
 * the redirect URI allow-list), instead of the name a client declares when it registers itself.
 */
final class KnownAssistants
{
    private const HOSTS = [
        'claude.ai' => 'Claude',
        'claude.com' => 'Claude',
        'chatgpt.com' => 'ChatGPT',
    ];

    public static function nameForHost(string $host): ?string
    {
        return self::HOSTS[strtolower($host)] ?? null;
    }

    private function __construct()
    {
    }
}
