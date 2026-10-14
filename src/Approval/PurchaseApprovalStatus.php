<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Approval;

use Sylius\Component\Core\Model\OrderInterface;
use Tmonier\SyliusB2bMcpPlugin\Order\OrderApprovalStatusInterface;
use Tmonier\SyliusB2bMcpPlugin\Order\Text;
use Tmonier\SyliusPurchaseApprovalPlugin\Repository\OrderApprovalRepositoryInterface;

/** The approval of an order the caller may already see (get_order checked the access). */
final readonly class PurchaseApprovalStatus implements OrderApprovalStatusInterface
{
    public function __construct(private OrderApprovalRepositoryInterface $approvalRepository)
    {
    }

    public function forOrder(OrderInterface $order): ?array
    {
        $approval = $this->approvalRepository->findOneByOrder($order);
        if (!$approval instanceof \Tmonier\SyliusPurchaseApprovalPlugin\Entity\OrderApprovalInterface) {
            return null;
        }
        $limit = $approval->getOrderLimit();

        return [
            'state' => $approval->getState(),
            'requested_at' => Text::date($approval->getCreatedAt()),
            'order_limit' => null === $limit ? null : Text::money($limit),
            'decided_at' => Text::date($approval->getDecidedAt()),
            'decided_by' => Text::clean($approval->getDeciderName(), 100),
            'comment' => Text::clean($approval->getComment(), 1000),
        ];
    }
}
