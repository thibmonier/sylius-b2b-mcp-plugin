<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Order;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Tmonier\SyliusB2bMcpPlugin\Order\OrderPresenter;

final class OrderPresenterTest extends TestCase
{
    public function testSummaryOwnOrder(): void
    {
        $order = $this->createOrderMock();
        $presenter = new OrderPresenter();

        $result = $presenter->summary($order);

        self::assertSame('000001', $result['number']);
        self::assertSame('new', $result['state']);
        self::assertSame('EUR', $result['currency']);
        self::assertSame('100.00', $result['total']);
        self::assertSame(2, $result['items_quantity']);
        self::assertArrayNotHasKey('placed_by', $result);
    }

    public function testSummaryCompanyOrderIncludesPlacedBy(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getFullName')->willReturn('Jane Doe');

        $order = $this->createOrderMock();
        $order->method('getCustomer')->willReturn($customer);

        $presenter = new OrderPresenter();
        $result = $presenter->summary($order, false);

        self::assertSame('Jane Doe', $result['placed_by']);
    }

    public function testCart(): void
    {
        $order = $this->createOrderMock();
        $order->method('getItems')->willReturn(new ArrayCollection());

        $presenter = new OrderPresenter();
        $result = $presenter->cart($order);

        self::assertSame('EUR', $result['currency']);
        self::assertSame('100.00', $result['total']);
        self::assertSame([], $result['items']);
    }

    private function createOrderMock(): OrderInterface&\PHPUnit\Framework\MockObject\MockObject
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getNumber')->willReturn('000001');
        $order->method('getCheckoutCompletedAt')->willReturn(null);
        $order->method('getState')->willReturn('new');
        $order->method('getPaymentState')->willReturn(null);
        $order->method('getShippingState')->willReturn(null);
        $order->method('getCurrencyCode')->willReturn('EUR');
        $order->method('getTotal')->willReturn(10000);
        $order->method('getTotalQuantity')->willReturn(2);
        $order->method('getItemsTotal')->willReturn(10000);
        $order->method('getShippingTotal')->willReturn(0);
        $order->method('getTaxTotal')->willReturn(0);
        $order->method('getOrderPromotionTotal')->willReturn(0);
        $order->method('getShipments')->willReturn(new ArrayCollection());
        $order->method('getPayments')->willReturn(new ArrayCollection());
        $order->method('getShippingAddress')->willReturn(null);
        $order->method('getItems')->willReturn(new ArrayCollection());

        return $order;
    }
}
