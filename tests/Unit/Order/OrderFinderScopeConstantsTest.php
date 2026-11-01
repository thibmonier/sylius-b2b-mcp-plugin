<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Order;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\Order\OrderFinder;

final class OrderFinderScopeConstantsTest extends TestCase
{
    public function testConstants(): void
    {
        self::assertSame('mine', OrderFinder::SCOPE_MINE);
        self::assertSame('company', OrderFinder::SCOPE_COMPANY);
    }
}
