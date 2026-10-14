<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth\Entity;

use League\OAuth2\Server\Entities\UserEntityInterface;

/** The "user" of the tokens is the connection (customer, assistant, channel) approved by the customer. */
final readonly class UserEntity implements UserEntityInterface
{
    /** @param non-empty-string $connectionId */
    public function __construct(private string $connectionId)
    {
    }

    /** @return non-empty-string */
    public function getIdentifier(): string
    {
        return $this->connectionId;
    }
}
