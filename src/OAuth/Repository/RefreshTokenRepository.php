<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth\Repository;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\OAuthToken;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Entity\RefreshTokenEntity;
use Tmonier\SyliusB2bMcpPlugin\OAuth\TokenStore;

/**
 * Refresh tokens rotate on every use. Presenting a refresh token that was already used
 * revokes every token of the connection (token theft: the legitimate client and the thief
 * both have to authenticate again, and only the customer can approve).
 */
final class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(private readonly TokenStore $store)
    {
    }

    public function getNewRefreshToken(): RefreshTokenEntityInterface
    {
        return new RefreshTokenEntity();
    }

    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        $accessToken = $refreshTokenEntity->getAccessToken();
        $connection = $this->store->findConnection($accessToken->getUserIdentifier());
        if (null === $connection || !$connection->isActive()) {
            throw OAuthServerException::invalidGrant('The connection was revoked.');
        }

        $this->store->store(
            OAuthToken::TYPE_REFRESH,
            $refreshTokenEntity->getIdentifier(),
            $connection,
            array_map(static fn (ScopeEntityInterface $scope): string => $scope->getIdentifier(), array_values($accessToken->getScopes())),
            $refreshTokenEntity->getExpiryDateTime(),
            $accessToken->getIdentifier(),
        );
    }

    public function revokeRefreshToken(string $tokenId): void
    {
        $this->store->revoke(OAuthToken::TYPE_REFRESH, $tokenId);
    }

    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        $token = $this->store->find(OAuthToken::TYPE_REFRESH, $tokenId);
        if (null === $token) {
            return true;
        }
        if (null !== $token->getRevokedAt()) {
            $this->store->revokeAllForConnection($token->getConnection());

            return true;
        }

        return !$token->getConnection()->isActive();
    }
}
