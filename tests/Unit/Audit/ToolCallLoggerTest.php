<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Audit;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tmonier\SyliusB2bMcpPlugin\Audit\ToolCallLogger;
use Tmonier\SyliusB2bMcpPlugin\Entity\ToolCall;

final class ToolCallLoggerTest extends TestCase
{
    public function testLogPersistsToolCall(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('isOpen')->willReturn(true);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(ToolCall::class));
        $em->expects(self::once())->method('flush');

        $logger = new ToolCallLogger($em, new NullLogger());
        $logger->log(null, 'get_order', ['number' => '000001'], 'success', null, 42, '127.0.0.1');
    }

    public function testLogSanitizesFreeTextArguments(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('isOpen')->willReturn(true);

        $persisted = null;
        $em->expects(self::once())->method('persist')->willReturnCallback(function (ToolCall $call) use (&$persisted): void {
            $persisted = $call;
        });
        $em->expects(self::once())->method('flush');

        $logger = new ToolCallLogger($em, new NullLogger());
        $logger->log(null, 'request_quote', ['message' => 'Please quote 10 units', 'comment' => 'Urgent'], 'success', null, 10, null);

        self::assertNotNull($persisted);
        /** @var array{message: string, comment: string} $args */
        $args = json_decode($persisted->getArguments(), true);
        self::assertSame('[21 characters]', $args['message']);
        self::assertSame('[6 characters]', $args['comment']);
    }

    public function testLogSwallowsExceptions(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('isOpen')->willReturn(true);
        $em->method('persist')->willThrowException(new \RuntimeException('DB down'));

        $logger = new ToolCallLogger($em, new NullLogger());
        $logger->log(null, 'test', [], 'error', null, 0, null);

        $this->addToAssertionCount(1);
    }

    public function testLogWhenEntityManagerClosed(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('isOpen')->willReturn(false);
        $em->expects(self::never())->method('persist');

        $logger = new ToolCallLogger($em, new NullLogger());
        $logger->log(null, 'test', [], 'error', null, 0, null);

        $this->addToAssertionCount(1);
    }
}
