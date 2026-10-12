<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth;

use League\OAuth2\Server\CryptTrait;

/** Reads the identifier of a refresh token (league encrypts its payload) for the revocation endpoint. */
final class RefreshTokenDecoder
{
    use CryptTrait;

    public function __construct(string $encryptionKey)
    {
        $this->encryptionKey = $encryptionKey;
    }

    public function getIdentifier(string $refreshToken): ?string
    {
        try {
            $payload = json_decode($this->decrypt($refreshToken), true, 4, \JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        $identifier = \is_array($payload) ? ($payload['refresh_token_id'] ?? null) : null;

        return \is_string($identifier) ? $identifier : null;
    }
}
