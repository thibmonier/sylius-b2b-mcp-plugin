<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Applies a change of `approvals.allow_decisions` at deploy time (cache warmup): connections
 * lose b2b:approve as soon as decisions are disabled. Best effort: no database at build time
 * (container images) is not an error, the first request or the purge command does it then.
 */
final class ApproveScopeCacheWarmer implements CacheWarmerInterface
{
    public function __construct(
        private readonly ApproveScopeGuard $guard,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        try {
            $this->guard->enforce();
        } catch (\Throwable $exception) {
            $this->logger->info('b2b:approve not swept at cache warmup (no database?), done on the next request.', ['exception' => $exception]);
        }

        return [];
    }
}
