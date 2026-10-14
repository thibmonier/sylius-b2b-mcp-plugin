<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Cart;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Tmonier\SyliusB2bMcpPlugin\Security\AuthenticatedConnection;

/**
 * The cart of the customer on the channel: the one the shop shows after login (latest non-empty
 * cart of the customer, see CustomerAndChannelBasedCartContext), or a new one.
 */
final readonly class CustomerCartProvider
{
    /**
     * @param OrderRepositoryInterface<OrderInterface> $orderRepository
     * @param FactoryInterface<OrderInterface> $orderFactory
     */
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private FactoryInterface $orderFactory,
    ) {
    }

    public function find(AuthenticatedConnection $connection): ?OrderInterface
    {
        return $this->orderRepository->findLatestNotEmptyCartByChannelAndCustomer($connection->channel, $connection->customer);
    }

    /** Not persisted when new: the cart adder persists it with its items. */
    public function getOrCreate(AuthenticatedConnection $connection): OrderInterface
    {
        $cart = $this->find($connection);
        if ($cart instanceof \Sylius\Component\Core\Model\OrderInterface) {
            return $cart;
        }

        $channel = $connection->channel;
        $cart = $this->orderFactory->createNew();
        $cart->setChannel($channel);
        $cart->setCurrencyCode($channel->getBaseCurrency()?->getCode());
        $cart->setLocaleCode($channel->getDefaultLocale()?->getCode());
        $cart->setCustomerWithAuthorization($connection->customer);

        return $cart;
    }
}
