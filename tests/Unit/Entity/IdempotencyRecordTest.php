<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\CustomerInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\IdempotencyRecord;

final class IdempotencyRecordTest extends TestCase
{
    public function testGetters(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $record = new IdempotencyRecord($customer, 'quick_order_add', 'key-123456', 'hash-abc');

        self::assertNull($record->getId());
        self::assertSame($customer, $record->getCustomer());
        self::assertSame('quick_order_add', $record->getTool());
        self::assertSame('key-123456', $record->getIdempotencyKey());
        self::assertSame('hash-abc', $record->getRequestHash());
        self::assertNull($record->getResponse());
        self::assertInstanceOf(\DateTimeImmutable::class, $record->getCreatedAt());
    }

    public function testSetResponse(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $record = new IdempotencyRecord($customer, 'reorder', 'key-000000', 'hash-xyz');

        $record->setResponse('{"result":"ok"}');

        self::assertSame('{"result":"ok"}', $record->getResponse());
    }
}
