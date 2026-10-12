<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Order;

use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;

/**
 * Company orders of the signed-in member (tmonier/sylius-company-accounts-plugin), decided by
 * the voters of that plugin. The null implementation is used when it is not installed.
 */
interface CompanyOrdersInterface
{
    public function isAvailable(): bool;

    /** Orders of the member's company in the channel (alias "o"), or null when not allowed. */
    public function createQueryBuilder(ChannelInterface $channel): ?QueryBuilder;

    public function findOrder(string $number, ChannelInterface $channel): ?OrderInterface;

    public function canView(OrderInterface $order): bool;
}
