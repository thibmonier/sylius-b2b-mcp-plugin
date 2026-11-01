<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Order;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Tmonier\SyliusB2bMcpPlugin\Order\CompanyOrdersInterface;
use Tmonier\SyliusB2bMcpPlugin\Order\NullCompanyOrders;

final class NullCompanyOrdersTest extends TestCase
{
    public function testIsNotAvailable(): void
    {
        $null = new NullCompanyOrders();

        self::assertInstanceOf(CompanyOrdersInterface::class, $null);
        self::assertFalse($null->isAvailable());
    }

    public function testCreateQueryBuilderReturnsNull(): void
    {
        self::assertNull((new NullCompanyOrders())->createQueryBuilder($this->createMock(ChannelInterface::class)));
    }

    public function testFindOrderReturnsNull(): void
    {
        self::assertNull((new NullCompanyOrders())->findOrder('123', $this->createMock(ChannelInterface::class)));
    }

    public function testCanViewReturnsFalse(): void
    {
        self::assertFalse((new NullCompanyOrders())->canView($this->createMock(OrderInterface::class)));
    }
}
