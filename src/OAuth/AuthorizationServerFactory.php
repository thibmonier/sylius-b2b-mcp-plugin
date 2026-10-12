<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

/** Authorization code grant with PKCE (S256 enforced by the authorization controller) and rotating refresh tokens. */
final class AuthorizationServerFactory
{
    public static function create(
        ClientRepositoryInterface $clientRepository,
        AccessTokenRepositoryInterface $accessTokenRepository,
        ScopeRepositoryInterface $scopeRepository,
        AuthCodeRepositoryInterface $authCodeRepository,
        RefreshTokenRepositoryInterface $refreshTokenRepository,
        string $encryptionKey,
        string $accessTokenTtl,
        string $refreshTokenTtl,
        string $authCodeTtl,
    ): AuthorizationServer {
        $server = new AuthorizationServer($clientRepository, $accessTokenRepository, $scopeRepository, new NullCryptKey(), $encryptionKey);
        $server->setDefaultScope(implode(' ', Scopes::DEFAULT));

        $accessTtl = new \DateInterval($accessTokenTtl);
        $refreshTtl = new \DateInterval($refreshTokenTtl);

        $authCodeGrant = new AuthCodeGrant($authCodeRepository, $refreshTokenRepository, new \DateInterval($authCodeTtl));
        $authCodeGrant->setRefreshTokenTTL($refreshTtl);
        $server->enableGrantType($authCodeGrant, $accessTtl);

        $refreshTokenGrant = new RefreshTokenGrant($refreshTokenRepository);
        $refreshTokenGrant->setRefreshTokenTTL($refreshTtl);
        $server->enableGrantType($refreshTokenGrant, $accessTtl);

        return $server;
    }
}
