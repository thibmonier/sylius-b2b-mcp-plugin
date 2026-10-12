<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth\Repository;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Tmonier\SyliusB2bMcpPlugin\OAuth\ApproveScopeGuard;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Entity\ScopeEntity;
use Tmonier\SyliusB2bMcpPlugin\OAuth\ScopeCatalog;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Scopes;
use Tmonier\SyliusB2bMcpPlugin\OAuth\TokenStore;

final class ScopeRepository implements ScopeRepositoryInterface
{
    public function __construct(
        private readonly TokenStore $store,
        private readonly ScopeCatalog $catalog,
        private readonly ?ApproveScopeGuard $approveScopeGuard = null,
    ) {
    }

    public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface
    {
        // Every known scope is accepted here, so that the refresh of a grant made before decisions
        // were disabled does not fail; finalizeScopes() drops what the shop does not offer now.
        return '' !== $identifier && \in_array($identifier, Scopes::ALL, true) ? new ScopeEntity($identifier) : null;
    }

    /** Never more than what the customer approved on the connection (also after a refresh). */
    public function finalizeScopes(array $scopes, string $grantType, ClientEntityInterface $clientEntity, ?string $userIdentifier = null, ?string $authCodeId = null): array
    {
        $connection = $this->store->findConnection($userIdentifier);
        if (null === $connection) {
            return [];
        }

        $this->approveScopeGuard?->enforceFor($connection);
        $approved = array_intersect($connection->getScopes(), $this->catalog->supported());

        return array_values(array_filter($scopes, static fn (ScopeEntityInterface $scope): bool => \in_array($scope->getIdentifier(), $approved, true)));
    }
}
