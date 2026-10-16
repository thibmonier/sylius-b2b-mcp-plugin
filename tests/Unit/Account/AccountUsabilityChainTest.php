<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Account;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\Customer;
use Tests\Tmonier\SyliusB2bMcpPlugin\Account\SwitchableChecker;
use Tmonier\SyliusB2bMcpPlugin\Account\AccountUsabilityChain;

final class AccountUsabilityChainTest extends TestCase
{
    public function testWithoutCheckerTheAccountIsUsable(): void
    {
        self::assertTrue((new AccountUsabilityChain([]))->check(new Customer())->isUsable());
    }

    public function testUsableWhenEveryCheckerAccepts(): void
    {
        $first = new SwitchableChecker();
        $second = new SwitchableChecker();

        $result = (new AccountUsabilityChain([$first, $second]))->check(new Customer());

        self::assertTrue($result->isUsable());
        self::assertNull($result->reason());
        self::assertSame([1, 1], [$first->calls, $second->calls]);
    }

    public function testTheFirstRefusalWinsAndItsReasonIsKept(): void
    {
        $first = new SwitchableChecker();
        $first->refusal = 'registration_pending';
        $second = new SwitchableChecker();
        $second->refusal = 'other';

        $result = (new AccountUsabilityChain([$first, $second]))->check(new Customer());

        self::assertFalse($result->isUsable());
        self::assertSame('registration_pending', $result->reason());
        self::assertSame(0, $second->calls, 'the chain stops at the first refusal');
    }

    public function testOneRefusingCheckerAmongSeveralRefuses(): void
    {
        $first = new SwitchableChecker();
        $second = new SwitchableChecker();
        $second->refusal = 'rejected';

        $result = (new AccountUsabilityChain([$first, $second]))->check(new Customer());

        self::assertFalse($result->isUsable());
        self::assertSame('rejected', $result->reason());
    }

    public function testACheckerThatThrowsRefusesAndTheErrorIsLoggedWithoutBeingExposed(): void
    {
        $checker = new SwitchableChecker();
        $checker->failure = new \RuntimeException('database password is hunter2');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(self::stringContains('failed'), self::callback(static fn (array $context): bool => $context['exception'] instanceof \RuntimeException));

        $result = (new AccountUsabilityChain([$checker], $logger))->check(new Customer());

        self::assertFalse($result->isUsable());
        self::assertSame('checker_failed', $result->reason());
        self::assertStringNotContainsString('hunter2', $result->reason());
    }

    public function testARefusalIsLoggedWithItsReason(): void
    {
        $checker = new SwitchableChecker();
        $checker->refusal = 'registration_pending';
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with(self::anything(), self::callback(static fn (array $context): bool => 'registration_pending' === $context['reason']));

        (new AccountUsabilityChain([$checker], $logger))->check(new Customer());
    }
}
