<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Account;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\Account\AccountUsability;

final class AccountUsabilityTest extends TestCase
{
    public function testUsable(): void
    {
        $result = AccountUsability::usable();

        self::assertTrue($result->isUsable());
        self::assertNull($result->reason());
    }

    public function testRefused(): void
    {
        $result = AccountUsability::refused('registration_pending');

        self::assertFalse($result->isUsable());
        self::assertSame('registration_pending', $result->reason());
    }
}
