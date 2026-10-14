<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Order;

use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Member\CurrentMemberResolverInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Repository\CompanyOrderRepositoryInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Security\CompanyPermissions;

/** Bridge to tmonier/sylius-company-accounts-plugin: its ORDER_VIEW_COMPANY voter decides. */
final readonly class CompanyAccountsOrders implements CompanyOrdersInterface
{
    public function __construct(
        private CurrentMemberResolverInterface $memberResolver,
        private CompanyOrderRepositoryInterface $companyOrderRepository,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function createQueryBuilder(ChannelInterface $channel): ?QueryBuilder
    {
        $company = $this->memberResolver->getMember()?->getCompany();
        if (!$company instanceof \Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface || !$this->authorizationChecker->isGranted(CompanyPermissions::ORDER_VIEW_COMPANY, $company)) {
            return null;
        }

        return $this->companyOrderRepository->createOrdersQueryBuilder($company)
            ->andWhere('o.channel = :tmonierB2bMcpChannel')
            ->setParameter('tmonierB2bMcpChannel', $channel)
        ;
    }

    public function findOrder(string $number, ChannelInterface $channel): ?OrderInterface
    {
        $company = $this->memberResolver->getMember()?->getCompany();
        if (!$company instanceof \Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface) {
            return null;
        }
        $order = $this->companyOrderRepository->findOrderByCompanyAndNumber($company, $number);
        if (!$order instanceof OrderInterface || $order->getChannel()?->getCode() !== $channel->getCode() || !$this->canView($order)) {
            return null;
        }

        return $order;
    }

    public function canView(OrderInterface $order): bool
    {
        return $this->authorizationChecker->isGranted(CompanyPermissions::ORDER_VIEW_COMPANY, $order);
    }
}
