<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Order;

use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class NullCompanyOrders implements CompanyOrdersInterface
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function createQueryBuilder(ChannelInterface $channel): ?QueryBuilder
    {
        return null;
    }

    public function findOrder(string $number, ChannelInterface $channel): ?OrderInterface
    {
        return null;
    }

    public function canView(OrderInterface $order): bool
    {
        return false;
    }
}
