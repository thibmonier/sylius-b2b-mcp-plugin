<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth\Repository;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\OAuthToken;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Entity\AuthCodeEntity;
use Tmonier\SyliusB2bMcpPlugin\OAuth\TokenStore;

/** Authorization codes are single use: a second redemption revokes the tokens of the connection (OAuth 2.1, 4.1.2). */
final class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    public function __construct(private readonly TokenStore $store)
    {
    }

    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return new AuthCodeEntity();
    }

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        $connection = $this->store->findConnection($authCodeEntity->getUserIdentifier());
        if (null === $connection || !$connection->isActive()) {
            throw OAuthServerException::accessDenied('The connection was revoked.');
        }

        $this->store->store(
            OAuthToken::TYPE_AUTH_CODE,
            $authCodeEntity->getIdentifier(),
            $connection,
            array_map(static fn (ScopeEntityInterface $scope): string => $scope->getIdentifier(), array_values($authCodeEntity->getScopes())),
            $authCodeEntity->getExpiryDateTime(),
        );
    }

    public function revokeAuthCode(string $codeId): void
    {
        $this->store->revoke(OAuthToken::TYPE_AUTH_CODE, $codeId);
    }

    public function isAuthCodeRevoked(string $codeId): bool
    {
        $code = $this->store->find(OAuthToken::TYPE_AUTH_CODE, $codeId);
        if (null === $code) {
            return true;
        }
        if (null !== $code->getRevokedAt()) {
            if ($this->store->isReplay($code)) {
                $this->store->revokeAllForConnection($code->getConnection());
            }

            return true;
        }

        return !$code->getConnection()->isActive();
    }
}
