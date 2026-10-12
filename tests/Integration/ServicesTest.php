<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Integration;

use Mcp\Exception\ToolCallException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Tmonier\SyliusB2bMcpPlugin\Functional\B2bMcpTestTrait;
use Tmonier\SyliusB2bMcpPlugin\Entity\OAuthToken;
use Tmonier\SyliusB2bMcpPlugin\Idempotency\IdempotencyStore;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\ListMyOrdersTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\ToolRefusal;
use Tmonier\SyliusB2bMcpPlugin\OAuth\TokenStore;

final class ServicesTest extends WebTestCase
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

    public function testAToolCalledWithoutAConnectedCustomerRefusesAndIsAudited(): void
    {
        // E.g. the stdio transport (bin/console mcp:server): no OAuth, no customer.
        $tool = self::getContainer()->get(ListMyOrdersTool::class);
        self::assertInstanceOf(ListMyOrdersTool::class, $tool);

        try {
            $tool();
            self::fail('A refusal was expected.');
        } catch (ToolCallException $exception) {
            self::assertSame('Not signed in: this tool needs a shop account connected to the assistant (OAuth).', $exception->getMessage());
        }
        self::assertSame('refused', $this->connection()->fetchOne("SELECT outcome FROM tmonier_b2b_mcp_tool_call WHERE tool = 'list_my_orders' AND customer_email IS NULL ORDER BY id DESC"));
    }

    public function testIdempotencyKeys(): void
    {
        $store = $this->service('tmonier_b2b_mcp.idempotency_store');
        self::assertInstanceOf(IdempotencyStore::class, $store);
        $customerId = self::idOf($this->customer());

        self::assertNull($store->claim($customerId, 'reorder', 'key-00001', ['order_number' => '1']));

        try {
            $store->claim($customerId, 'reorder', 'key-00001', ['order_number' => '1']);
            self::fail('The running call holds the key.');
        } catch (ToolRefusal $refusal) {
            self::assertStringContainsString('still running', $refusal->getMessage());
        }

        $store->complete($customerId, 'reorder', 'key-00001', ['applied' => true]);
        self::assertSame(['applied' => true], $store->claim($customerId, 'reorder', 'key-00001', ['order_number' => '1']));
        // Keys are scoped by customer and tool.
        self::assertNull($store->claim($customerId, 'request_quote', 'key-00001', ['order_number' => '1']));
        self::assertNull($store->claim(self::idOf($this->customer('Eve', 'Other')), 'reorder', 'key-00001', ['order_number' => '1']));

        // A failed call releases its key.
        self::assertNull($store->claim($customerId, 'reorder', 'key-00002', []));
        $store->release($customerId, 'reorder', 'key-00002');
        self::assertNull($store->claim($customerId, 'reorder', 'key-00002', []));

        // An abandoned claim (crash) can be taken over after a few minutes.
        $this->connection()->executeStatement("UPDATE tmonier_b2b_mcp_idempotency SET created_at = ? WHERE idempotency_key = 'key-00002'", [(new \DateTimeImmutable('-10 minutes'))->format('Y-m-d H:i:s')]);
        self::assertNull($store->claim($customerId, 'reorder', 'key-00002', ['other' => 'arguments']));

        self::assertFalse(IdempotencyStore::isValidKey('short'));
        self::assertFalse(IdempotencyStore::isValidKey('with space'));
        self::assertTrue(IdempotencyStore::isValidKey('6f1c2b4e-9a0d-4c8e-8f7a-1b2c3d4e5f60'));
    }

    public function testThePurgeCommandDeletesExpiredData(): void
    {
        $customer = $this->customer();
        $connection = null;
        $this->accessToken($customer, connection: $connection);
        $store = $this->service('tmonier_b2b_mcp.oauth.token_store');
        self::assertInstanceOf(TokenStore::class, $store);
        $store->store(OAuthToken::TYPE_ACCESS, 'expired-token-0000000000000000', $connection, [], new \DateTimeImmutable('-3 days'));
        $this->connection()->executeStatement('INSERT INTO tmonier_b2b_mcp_idempotency (customer_id, tool, idempotency_key, request_hash, response, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$customer->getId(), 'reorder', 'old-key-001', 'x', '{}', (new \DateTimeImmutable('-2 days'))->format('Y-m-d H:i:s')]);
        $this->connection()->executeStatement("INSERT INTO tmonier_b2b_mcp_tool_call (tool, arguments, outcome, duration_ms, created_at) VALUES ('get_order', '{}', 'success', 1, ?)", [(new \DateTimeImmutable('-100 days'))->format('Y-m-d H:i:s')]);

        $tester = $this->command('tmonier:b2b-mcp:purge');
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertSame(0, $this->sqlInt('SELECT COUNT(*) FROM tmonier_b2b_mcp_token WHERE identifier_hash = ?', [hash('sha256', 'expired-token-0000000000000000')]));
        self::assertSame(1, $this->sqlInt('SELECT COUNT(*) FROM tmonier_b2b_mcp_token WHERE connection_id = ?', [$connection->getId()]), 'Valid tokens stay.');
        self::assertSame(0, $this->sqlInt("SELECT COUNT(*) FROM tmonier_b2b_mcp_idempotency WHERE idempotency_key = 'old-key-001'"));
        self::assertSame(0, $this->sqlInt('SELECT COUNT(*) FROM tmonier_b2b_mcp_tool_call WHERE created_at < ?', [(new \DateTimeImmutable('-90 days'))->format('Y-m-d H:i:s')]));
    }

    public function testTheCreateClientCommand(): void
    {
        $tester = $this->command('tmonier:b2b-mcp:create-client');

        $tester->execute(['name' => 'Claude', '--redirect-uri' => ['https://evil.example.com/cb']]);
        self::assertSame(2, $tester->getStatusCode());

        $tester->execute(['name' => 'Claude', '--redirect-uri' => ['https://claude.ai/api/mcp/auth_callback'], '--confidential' => true]);
        $tester->assertCommandIsSuccessful();
        self::assertMatchesRegularExpression('/Client secret \(shown once\): [0-9a-f]{64}/', $tester->getDisplay());
        self::assertSame(1, preg_match('/Client ID: ([0-9a-f]{32})/', $tester->getDisplay(), $matches));
        $hash = $this->sqlValue('SELECT secret_hash FROM tmonier_b2b_mcp_client WHERE identifier = ?', [$matches[1] ?? '']);
        self::assertIsString($hash);
        self::assertStringStartsWith('$', $hash, 'Only a hash of the secret is stored.');
    }

    private static function idOf(\Sylius\Component\Core\Model\CustomerInterface $customer): int
    {
        $id = $customer->getId();
        self::assertIsInt($id);

        return $id;
    }

    private function command(string $name): CommandTester
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        return new CommandTester((new Application($kernel))->find($name));
    }
}
