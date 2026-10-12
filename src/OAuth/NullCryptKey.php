<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth;

use League\OAuth2\Server\CryptKeyInterface;

/**
 * Access tokens are opaque random strings checked against the database (see
 * Entity\AccessTokenEntity::toString()), not signed JWTs: no key pair to manage.
 */
final class NullCryptKey implements CryptKeyInterface
{
    public function getKeyPath(): string
    {
        return '';
    }

    public function getPassPhrase(): ?string
    {
        return null;
    }

    public function getKeyContents(): string
    {
        return '';
    }
}
