<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Tmonier\SyliusB2bMcpPlugin\Audit\AuditMirrorInterface;
use Tmonier\SyliusB2bMcpPlugin\Audit\NullAuditMirror;
use Tmonier\SyliusB2bMcpPlugin\Entity\ConnectionInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\OAuthClient;
use Tmonier\SyliusB2bMcpPlugin\Repository\ConnectionRepository;

/** Consent of a customer to an assistant, and its revocation. */
final readonly class ConnectionManager
{
    /** @param FactoryInterface<ConnectionInterface> $connectionFactory */
    public function __construct(
        private ConnectionRepository $connectionRepository,
        private FactoryInterface $connectionFactory,
        private EntityManagerInterface $entityManager,
        private TokenStore $tokenStore,
        private AuditMirrorInterface $mirror = new NullAuditMirror(),
    ) {
    }

    /**
     * Approves the scopes for the assistant on the channel: the active connection is reused
     * (its scopes replaced by the approved ones, its tokens revoked if a scope is withdrawn), or a
     * new one is created.
     *
     * @param list<string> $scopes
     */
    public function approve(CustomerInterface $customer, OAuthClient $client, string $channelCode, array $scopes): ConnectionInterface
    {
        $connection = $this->connectionRepository->findActive($customer, $client, $channelCode);
        if (!$connection instanceof \Tmonier\SyliusB2bMcpPlugin\Entity\ConnectionInterface) {
            $connection = $this->connectionFactory->createNew();
            $connection->setCustomer($customer);
            $connection->setClient($client);
            $connection->setChannelCode($channelCode);
            $this->entityManager->persist($connection);
        }
        $previous = null === $connection->getId() ? [] : $connection->getScopes();
        $connection->setScopes(Scopes::normalize($scopes));
        $this->entityManager->flush();
        // Fewer permissions than before: the tokens issued with the old ones stop at once.
        if ([] !== array_diff($previous, $connection->getScopes())) {
            $this->tokenStore->revokeAllForConnection($connection);
        }
        if ($previous !== $connection->getScopes()) {
            $this->mirror->connectionApproved($connection, $previous);
        }

        return $connection;
    }

    public function revoke(ConnectionInterface $connection): void
    {
        $connection->revoke($this->tokenStore->now());
        $this->entityManager->flush();
        $this->tokenStore->revokeAllForConnection($connection);
        $this->mirror->connectionRevoked($connection);
    }
}
