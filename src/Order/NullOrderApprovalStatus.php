<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Order;

use Sylius\Component\Core\Model\OrderInterface;

final class NullOrderApprovalStatus implements OrderApprovalStatusInterface
{
    public function forOrder(OrderInterface $order): ?array
    {
        return null;
    }
}
