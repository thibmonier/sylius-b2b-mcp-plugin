<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth\Repository;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Entity\ClientEntity;
use Tmonier\SyliusB2bMcpPlugin\OAuth\TokenStore;

final class ClientRepository implements ClientRepositoryInterface
{
    public const GRANT_TYPES = ['authorization_code', 'refresh_token'];

    public function __construct(private readonly TokenStore $store)
    {
    }

    public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface
    {
        $client = $this->store->findClient($clientIdentifier);

        return null === $client ? null : new ClientEntity($client);
    }

    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        $client = $this->store->findClient($clientIdentifier);
        if (null === $client || (null !== $grantType && !\in_array($grantType, self::GRANT_TYPES, true))) {
            return false;
        }

        $secretHash = $client->getSecretHash();
        if (null === $secretHash) {
            return null === $clientSecret || '' === $clientSecret;
        }

        return null !== $clientSecret && password_verify($clientSecret, $secretHash);
    }
}
