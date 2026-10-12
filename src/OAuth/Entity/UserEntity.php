<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth\Entity;

use League\OAuth2\Server\Entities\UserEntityInterface;

/** The "user" of the tokens is the connection (customer, assistant, channel) approved by the customer. */
final class UserEntity implements UserEntityInterface
{
    public function __construct(private readonly string $connectionId)
    {
    }

    public function getIdentifier(): string
    {
        return $this->connectionId;
    }
}
