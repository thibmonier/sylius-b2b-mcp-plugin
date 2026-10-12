<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\ConnectionInterface;

/**
 * Keeps b2b:approve in line with `approvals.allow_decisions`. While decisions are disabled, the
 * scope is removed from the connections for good (not only ignored): turning decisions back on
 * never gives it back without a new consent of the customer.
 *
 * The sweep runs on the first MCP request or token refresh of each disabled period (a cache
 * flag avoids repeating it), at cache warmup (deploy of the configuration change) and in
 * `tmonier:b2b-mcp:purge`.
 */
final class ApproveScopeGuard
{
    private const SWEPT_FLAG = 'tmonier_b2b_mcp.approve_scope_swept';

    /** @param class-string<ConnectionInterface> $connectionClass */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ScopeCatalog $catalog,
        private readonly CacheItemPoolInterface $cache,
        private readonly string $connectionClass,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->catalog->isSupported(Scopes::APPROVE);
    }

    /** Sweep once per disabled period (no-op while enabled, which resets the flag). */
    public function enforce(): void
    {
        if ($this->isEnabled()) {
            if ($this->cache->hasItem(self::SWEPT_FLAG)) {
                $this->cache->deleteItem(self::SWEPT_FLAG);
            }

            return;
        }
        if ($this->cache->getItem(self::SWEPT_FLAG)->isHit()) {
            return;
        }
        $this->sweep();
        $this->cache->save($this->cache->getItem(self::SWEPT_FLAG)->set(true));
    }

    /**
     * Removes b2b:approve from every connection when decisions are disabled.
     *
     * @return int connections changed
     */
    public function sweep(): int
    {
        if ($this->isEnabled()) {
            return 0;
        }

        $changed = 0;
        /** @var iterable<ConnectionInterface> $connections */
        $connections = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from($this->connectionClass, 'c')
            ->getQuery()
            ->toIterable()
        ;
        foreach ($connections as $connection) {
            if ($connection->hasScope(Scopes::APPROVE)) {
                $this->strip($connection);
                ++$changed;
            }
        }
        $this->entityManager->flush();

        return $changed;
    }

    /** One connection, persisted at once (refresh and authentication of a disabled period). */
    public function enforceFor(ConnectionInterface $connection): void
    {
        if (!$this->isEnabled() && $connection->hasScope(Scopes::APPROVE)) {
            $this->strip($connection);
            $this->entityManager->flush();
        }
    }

    private function strip(ConnectionInterface $connection): void
    {
        $connection->setScopes(array_values(array_diff($connection->getScopes(), [Scopes::APPROVE])));
    }
}
