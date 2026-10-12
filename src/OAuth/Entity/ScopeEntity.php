<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth\Entity;

use League\OAuth2\Server\Entities\ScopeEntityInterface;

final class ScopeEntity implements ScopeEntityInterface
{
    /** @param non-empty-string $identifier */
    public function __construct(private readonly string $identifier)
    {
    }

    /** @return non-empty-string */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function jsonSerialize(): string
    {
        return $this->identifier;
    }
}
