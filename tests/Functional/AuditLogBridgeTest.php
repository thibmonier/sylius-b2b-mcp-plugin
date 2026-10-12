<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Mirror into tmonier/sylius-audit-log-plugin (enabled in the test application). */
final class AuditLogBridgeTest extends WebTestCase
{
    use B2bMcpTestTrait;

    protected function setUp(): void
    {
        $this->setUpClient();
    }

    protected function tearDown(): void
    {
        $this->tearDownClient();
        parent::tearDown();
    }

    public function testToolCallsConsentsAndRevocationsAreMirroredInTheCustomerHistory(): void
    {
        $customer = $this->customer();
        $customerId = (string) self::intAt(['id' => $customer->getId()], 'id');
        $connection = null;
        $token = $this->accessToken($customer, connection: $connection);
        $this->callTool($token, 'request_quote', ['source' => 'lines', 'lines' => [['sku' => (string) $this->variant()->getCode(), 'quantity' => 1]], 'message' => 'Secret phone 0612345678', 'idempotency_key' => 'mirror-quote-1']);

        /** @var list<array{action: string, resource_type: string, resource_label: string, changes: string, actor_type: string, actor_id: int|string|null}> $rows */
        $rows = $this->connection()->fetchAllAssociative("SELECT action, resource_type, resource_label, changes, actor_type, actor_id FROM tmonier_audit_log_entry WHERE owner_type = 'customer' AND owner_id = ? AND resource_type LIKE 'mcp\_%' ORDER BY id", [$customerId]);
        self::assertSame(['mcp_connection', 'mcp_tool_call'], array_column($rows, 'resource_type'));
        self::assertSame('create', $rows[0]['action']);
        self::assertStringContainsString('b2b:read b2b:cart', $rows[0]['changes']);
        self::assertSame('request_quote (success)', $rows[1]['resource_label']);
        self::assertSame('customer', $rows[1]['actor_type']);
        self::assertSame($customerId, (string) $rows[1]['actor_id']);
        self::assertStringNotContainsString('0612345678', $rows[1]['changes'], 'Arguments are never mirrored.');

        $this->client->getCookieJar()->clear();
        $this->loginShop($customer);
        $crawler = $this->client->request('GET', '/en_US/account/ai-assistants');
        $this->client->submit($crawler->filter(sprintf('[data-test-tmonier-b2b-mcp-revoke="%d"]', $connection->getId()))->form());

        $revocation = $this->sqlValue("SELECT changes FROM tmonier_audit_log_entry WHERE resource_type = 'mcp_connection' AND action = 'update' AND resource_id = ?", [(string) self::intAt(['id' => $connection->getId()], 'id')]);
        self::assertIsString($revocation);
        self::assertStringContainsString('revokedAt', $revocation);
    }
}
