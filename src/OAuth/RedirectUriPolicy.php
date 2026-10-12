<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth;

/**
 * Which redirect URIs an assistant may register and use. Open dynamic registration lets anyone
 * create a client: restricting the redirect URIs to the known assistants keeps a phishing client
 * from receiving the authorization codes of customers.
 */
final class RedirectUriPolicy
{
    /** @param list<string> $allowedUris exact URIs, or ending with "*" for one path segment */
    public function __construct(
        private readonly array $allowedUris,
        private readonly bool $allowLoopback,
    ) {
    }

    public function isAllowed(string $uri): bool
    {
        if (\strlen($uri) > 2000 || 1 === preg_match('/[\x00-\x20#]/', $uri)) {
            return false;
        }

        if ($this->allowLoopback && 1 === preg_match('#^http://(localhost|127\.0\.0\.1|\[::1\])(:\d{1,5})?(/[A-Za-z0-9._~/-]*)?$#', $uri)) {
            return true;
        }

        foreach ($this->allowedUris as $allowed) {
            if (str_ends_with($allowed, '*')) {
                $prefix = substr($allowed, 0, -1);
                if (str_starts_with($uri, $prefix) && 1 === preg_match('/^[A-Za-z0-9._~-]+$/', substr($uri, \strlen($prefix)))) {
                    return true;
                }

                continue;
            }
            if ($uri === $allowed) {
                return true;
            }
        }

        return false;
    }
}
