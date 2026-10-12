<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Order;

use Sylius\Component\Core\Model\OrderInterface;

/** Purchase approval of an order (tmonier/sylius-purchase-approval-plugin), for get_order. */
interface OrderApprovalStatusInterface
{
    /** @return array<string, mixed>|null null when the order needs no approval or the plugin is absent */
    public function forOrder(OrderInterface $order): ?array;
}
