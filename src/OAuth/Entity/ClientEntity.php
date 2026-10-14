<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth\Entity;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\OAuthClient;

final readonly class ClientEntity implements ClientEntityInterface
{
    public function __construct(private OAuthClient $client)
    {
    }

    public function getClient(): OAuthClient
    {
        return $this->client;
    }

    /** @return non-empty-string */
    public function getIdentifier(): string
    {
        $identifier = $this->client->getIdentifier();
        if ('' === $identifier) {
            throw new \LogicException('An OAuth client has an identifier.');
        }

        return $identifier;
    }

    public function getName(): string
    {
        return $this->client->getName();
    }

    /** @return list<string> */
    public function getRedirectUri(): array
    {
        return $this->client->getRedirectUris();
    }

    public function isConfidential(): bool
    {
        return $this->client->isConfidential();
    }
}
