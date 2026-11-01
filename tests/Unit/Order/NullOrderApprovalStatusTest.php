<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Order;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Tmonier\SyliusB2bMcpPlugin\Order\NullOrderApprovalStatus;
use Tmonier\SyliusB2bMcpPlugin\Order\OrderApprovalStatusInterface;

final class NullOrderApprovalStatusTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(OrderApprovalStatusInterface::class, new NullOrderApprovalStatus());
    }

    public function testForOrderReturnsNull(): void
    {
        self::assertNull((new NullOrderApprovalStatus())->forOrder($this->createMock(OrderInterface::class)));
    }
}
