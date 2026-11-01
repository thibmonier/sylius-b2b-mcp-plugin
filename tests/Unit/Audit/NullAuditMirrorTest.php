<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Audit;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\Audit\AuditMirrorInterface;
use Tmonier\SyliusB2bMcpPlugin\Audit\NullAuditMirror;
use Tmonier\SyliusB2bMcpPlugin\Entity\ConnectionInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\ToolCallInterface;

final class NullAuditMirrorTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(AuditMirrorInterface::class, new NullAuditMirror());
    }

    public function testAllMethodsAreNoOp(): void
    {
        $mirror = new NullAuditMirror();

        $mirror->toolCalled($this->createMock(ToolCallInterface::class));
        $mirror->connectionApproved($this->createMock(ConnectionInterface::class), ['b2b:read']);
        $mirror->connectionRevoked($this->createMock(ConnectionInterface::class));

        $this->addToAssertionCount(1);
    }
}
