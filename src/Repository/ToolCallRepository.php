<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Repository;

use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Tmonier\SyliusB2bMcpPlugin\Entity\ToolCallInterface;

/**
 * @extends EntityRepository<ToolCallInterface>
 */
class ToolCallRepository extends EntityRepository
{
    public function deleteOlderThan(\DateTimeImmutable $date): int
    {
        $deleted = $this->createQueryBuilder('o')
            ->delete()
            ->andWhere('o.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute()
        ;

        return \is_int($deleted) ? $deleted : 0;
    }
}
