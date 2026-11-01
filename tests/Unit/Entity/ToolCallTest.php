<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\CustomerInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\Connection;
use Tmonier\SyliusB2bMcpPlugin\Entity\OAuthClient;
use Tmonier\SyliusB2bMcpPlugin\Entity\ToolCall;
use Tmonier\SyliusB2bMcpPlugin\Entity\ToolCallInterface;

final class ToolCallTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $call = $this->createToolCall();

        self::assertInstanceOf(ToolCallInterface::class, $call);
    }

    public function testGetters(): void
    {
        $call = $this->createToolCall();

        self::assertNull($call->getId());
        self::assertSame('b2b@example.com', $call->getCustomerEmail());
        self::assertSame('Claude', $call->getClientName());
        self::assertSame('WEB', $call->getChannelCode());
        self::assertSame('get_order', $call->getTool());
        self::assertSame('{"number":"000001"}', $call->getArguments());
        self::assertSame('success', $call->getOutcome());
        self::assertNull($call->getMessage());
        self::assertSame(42, $call->getDurationMs());
        self::assertSame('127.0.0.1', $call->getIpAddress());
        self::assertInstanceOf(\DateTimeImmutable::class, $call->getCreatedAt());
    }

    public function testNullConnection(): void
    {
        $call = new ToolCall(null, null, null, null, 'test', '{}', 'error', 'refused', 0, null);

        self::assertNull($call->getConnection());
        self::assertNull($call->getCustomerEmail());
        self::assertNull($call->getClientName());
        self::assertNull($call->getChannelCode());
        self::assertNull($call->getCustomer());
        self::assertNull($call->getIpAddress());
    }

    public function testCustomerFromConnection(): void
    {
        $call = $this->createToolCall();

        self::assertInstanceOf(CustomerInterface::class, $call->getCustomer());
    }

    private function createToolCall(): ToolCall
    {
        $connection = new Connection();
        $connection->setClient(new OAuthClient('id', 'Claude', []));
        $connection->setChannelCode('WEB');
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn('b2b@example.com');
        $connection->setCustomer($customer);

        return new ToolCall(
            $connection,
            'b2b@example.com',
            'Claude',
            'WEB',
            'get_order',
            '{"number":"000001"}',
            'success',
            null,
            42,
            '127.0.0.1',
        );
    }
}
