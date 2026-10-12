<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth\Entity;

use League\OAuth2\Server\CryptKeyInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

/** Opaque bearer token: the random identifier itself (80 hexadecimal characters). */
final class AccessTokenEntity implements AccessTokenEntityInterface
{
    use EntityTrait;
    use TokenEntityTrait;

    public function setPrivateKey(CryptKeyInterface $privateKey): void
    {
    }

    public function toString(): string
    {
        return $this->getIdentifier();
    }
}
