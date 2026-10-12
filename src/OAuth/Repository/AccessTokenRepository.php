<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth\Repository;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\OAuthToken;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Entity\AccessTokenEntity;
use Tmonier\SyliusB2bMcpPlugin\OAuth\TokenStore;

final class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    public function __construct(private readonly TokenStore $store)
    {
    }

    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, ?string $userIdentifier = null): AccessTokenEntityInterface
    {
        $token = new AccessTokenEntity();
        $token->setClient($clientEntity);
        foreach ($scopes as $scope) {
            $token->addScope($scope);
        }
        if (null !== $userIdentifier) {
            $token->setUserIdentifier($userIdentifier);
        }

        return $token;
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        $connection = $this->store->findConnection($accessTokenEntity->getUserIdentifier());
        if (null === $connection || !$connection->isActive()) {
            throw OAuthServerException::invalidGrant('The connection was revoked.');
        }

        $this->store->store(
            OAuthToken::TYPE_ACCESS,
            $accessTokenEntity->getIdentifier(),
            $connection,
            array_map(static fn (ScopeEntityInterface $scope): string => $scope->getIdentifier(), array_values($accessTokenEntity->getScopes())),
            $accessTokenEntity->getExpiryDateTime(),
        );
    }

    public function revokeAccessToken(string $tokenId): void
    {
        $this->store->revoke(OAuthToken::TYPE_ACCESS, $tokenId);
    }

    public function isAccessTokenRevoked(string $tokenId): bool
    {
        $token = $this->store->find(OAuthToken::TYPE_ACCESS, $tokenId);

        return null === $token || null !== $token->getRevokedAt() || !$token->getConnection()->isActive();
    }
}
