<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\ConnectionInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\OAuthClient;
use Tmonier\SyliusB2bMcpPlugin\Repository\ConnectionRepository;

/** Consent of a customer to an assistant, and its revocation. */
final class ConnectionManager
{
    /** @param FactoryInterface<ConnectionInterface> $connectionFactory */
    public function __construct(
        private readonly ConnectionRepository $connectionRepository,
        private readonly FactoryInterface $connectionFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenStore $tokenStore,
    ) {
    }

    /**
     * Approves the scopes for the assistant on the channel: the active connection is reused
     * (its scopes replaced by the approved ones), or a new one is created.
     *
     * @param list<string> $scopes
     */
    public function approve(CustomerInterface $customer, OAuthClient $client, string $channelCode, array $scopes): ConnectionInterface
    {
        $connection = $this->connectionRepository->findActive($customer, $client, $channelCode);
        if (null === $connection) {
            $connection = $this->connectionFactory->createNew();
            $connection->setCustomer($customer);
            $connection->setClient($client);
            $connection->setChannelCode($channelCode);
            $this->entityManager->persist($connection);
        }
        $connection->setScopes(Scopes::normalize($scopes));
        $this->entityManager->flush();

        return $connection;
    }

    public function revoke(ConnectionInterface $connection): void
    {
        $connection->revoke($this->tokenStore->now());
        $this->entityManager->flush();
        $this->tokenStore->revokeAllForConnection($connection);
    }
}
