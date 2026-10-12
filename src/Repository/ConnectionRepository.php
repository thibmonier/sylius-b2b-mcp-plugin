<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\CustomerInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\ConnectionInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\OAuthClient;

/**
 * @extends EntityRepository<ConnectionInterface>
 */
class ConnectionRepository extends EntityRepository
{
    public function findActive(CustomerInterface $customer, OAuthClient $client, string $channelCode): ?ConnectionInterface
    {
        /** @var ConnectionInterface|null $connection */
        $connection = $this->createQueryBuilder('o')
            ->andWhere('o.customer = :customer')
            ->andWhere('o.client = :client')
            ->andWhere('o.channelCode = :channelCode')
            ->andWhere('o.revokedAt IS NULL')
            ->setParameter('customer', $customer)
            ->setParameter('client', $client)
            ->setParameter('channelCode', $channelCode)
            ->orderBy('o.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $connection;
    }

    /** @return list<ConnectionInterface> */
    public function findActiveByCustomer(CustomerInterface $customer): array
    {
        /** @var list<ConnectionInterface> $connections */
        $connections = $this->createQueryBuilder('o')
            ->addSelect('client')
            ->innerJoin('o.client', 'client')
            ->andWhere('o.customer = :customer')
            ->andWhere('o.revokedAt IS NULL')
            ->setParameter('customer', $customer)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $connections;
    }

    /** Admin grid. */
    public function createListQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->addSelect('client', 'customer')
            ->innerJoin('o.client', 'client')
            ->innerJoin('o.customer', 'customer')
        ;
    }
}
