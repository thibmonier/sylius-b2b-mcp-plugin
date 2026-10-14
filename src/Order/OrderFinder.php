<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Order;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Tmonier\SyliusB2bMcpPlugin\Mcp\ToolRefusal;
use Tmonier\SyliusB2bMcpPlugin\Security\AuthenticatedConnection;

/**
 * Placed orders the connected customer may see on the channel of the request: their own, plus
 * the orders of their company when the company accounts plugin grants it. Unknown and forbidden
 * orders get the same answer.
 */
final readonly class OrderFinder
{
    public const SCOPE_MINE = 'mine';

    public const SCOPE_COMPANY = 'company';

    /** @param OrderRepositoryInterface<OrderInterface> $orderRepository */
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private CompanyOrdersInterface $companyOrders,
    ) {
    }

    /** @return array{orders: list<OrderInterface>, total: int} */
    public function list(AuthenticatedConnection $connection, string $scope, int $page, int $limit): array
    {
        if (self::SCOPE_COMPANY === $scope) {
            if (!$this->companyOrders->isAvailable()) {
                throw new ToolRefusal('Company orders are not available on this shop.');
            }
            $queryBuilder = $this->companyOrders->createQueryBuilder($connection->channel);
            if (!$queryBuilder instanceof \Doctrine\ORM\QueryBuilder) {
                throw new ToolRefusal('Your company role does not give access to the company orders.');
            }
        } else {
            $queryBuilder = $this->createOwnQueryBuilder($connection);
        }

        $queryBuilder->setFirstResult(($page - 1) * $limit)->setMaxResults($limit);
        $paginator = new Paginator($queryBuilder);
        $orders = [];
        foreach ($paginator as $order) {
            if ($order instanceof OrderInterface) {
                $orders[] = $order;
            }
        }

        return ['orders' => $orders, 'total' => \count($paginator)];
    }

    /** @throws ToolRefusal */
    public function get(AuthenticatedConnection $connection, string $number): OrderInterface
    {
        $number = ltrim(trim($number), '#');
        $order = '' === $number ? null : $this->orderRepository->findOneByNumberAndCustomer($number, $connection->customer);
        if (
            $order instanceof OrderInterface &&
            OrderInterface::STATE_CART !== $order->getState() &&
            $order->getChannel()?->getCode() === $connection->channel->getCode()
        ) {
            return $order;
        }

        $order = '' === $number ? null : $this->companyOrders->findOrder($number, $connection->channel);
        if ($order instanceof \Sylius\Component\Core\Model\OrderInterface && OrderInterface::STATE_CART !== $order->getState()) {
            return $order;
        }

        throw new ToolRefusal(\sprintf('No order "%s" visible to you on this shop.', mb_substr($number, 0, 64)));
    }

    public function isOwn(AuthenticatedConnection $connection, OrderInterface $order): bool
    {
        $owner = $order->getCustomer();

        return $owner instanceof \Sylius\Component\Customer\Model\CustomerInterface && null !== $owner->getId() && $owner->getId() === $connection->customer->getId();
    }

    private function createOwnQueryBuilder(AuthenticatedConnection $connection): QueryBuilder
    {
        $queryBuilder = $this->orderRepository->createByCustomerAndChannelIdQueryBuilder($connection->customer->getId(), $connection->channel->getId());

        return $queryBuilder
            ->addOrderBy('o.checkoutCompletedAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
        ;
    }
}
