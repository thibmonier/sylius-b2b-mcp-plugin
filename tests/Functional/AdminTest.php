<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Functional;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusB2bMcpPlugin\Entity\ConnectionInterface;

/** Admin: connections of the assistants (with revocation) and audit log of the tool calls. */
final class AdminTest extends WebTestCase
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

    public function testTheMerchantSeesAndRevokesTheConnections(): void
    {
        $customer = $this->customer();
        $connection = null;
        $token = $this->accessToken($customer, connection: $connection);
        self::assertInstanceOf(ConnectionInterface::class, $connection);
        $this->callTool($token, 'list_my_orders');
        $this->client->getCookieJar()->clear();
        $this->loginAdmin();

        $crawler = $this->client->request('GET', '/admin/ai-assistants/connections/');
        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('AI assistant connections', $content);
        self::assertStringContainsString((string) $customer->getEmail(), $content);
        self::assertStringNotContainsString('tmonier_b2b_mcp.', $content, 'Every label is translated.');
        // Compact scopes (code, description as tooltip) so that the grid fits the screen.
        $scope = $crawler->filter('[data-test-tmonier-b2b-mcp-scope="b2b:read"]')->first();
        self::assertSame('b2b:read', trim($scope->text()));
        self::assertStringStartsWith('Read your orders', (string) $scope->attr('title'));

        $this->client->submit($crawler->filter(sprintf('[data-test-tmonier-b2b-mcp-admin-revoke="%d"] button', $connection->getId()))->form());
        self::assertResponseRedirects('/admin/ai-assistants/connections/');
        self::assertNotNull($this->connection()->fetchOne('SELECT revoked_at FROM tmonier_b2b_mcp_connection WHERE id = ?', [$connection->getId()]));

        $this->client->getCookieJar()->clear();
        $this->post($token, ['jsonrpc' => '2.0', 'id' => 42, 'method' => 'tools/list']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testTheRevocationNeedsAValidCsrfToken(): void
    {
        $connection = null;
        $this->accessToken($this->customer(), connection: $connection);
        self::assertInstanceOf(ConnectionInterface::class, $connection);
        $this->loginAdmin();

        $this->client->request('POST', sprintf('/admin/ai-assistants/connections/%d/revoke', $connection->getId()), ['_csrf_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
        self::assertNull($this->connection()->fetchOne('SELECT revoked_at FROM tmonier_b2b_mcp_connection WHERE id = ?', [$connection->getId()]));
    }

    public function testTheAuditLogListsTheToolCalls(): void
    {
        $customer = $this->customer();
        $token = $this->accessToken($customer);
        $this->callTool($token, 'get_order', ['order_number' => 'missing-order']);
        $this->callTool($token, 'get_checkout_link');
        $this->client->getCookieJar()->clear();
        $this->loginAdmin();

        $crawler = $this->client->request('GET', '/admin/ai-assistants/tool-calls/?criteria[customerEmail][value]=' . urlencode((string) $customer->getEmail()));

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('AI assistant tool calls', $content);
        self::assertStringContainsString('get_order', $content);
        self::assertStringContainsString('Refused', $content);
        self::assertStringContainsString('missing-order', $content);
        self::assertCount(1, $crawler->filter('[data-test-tmonier-b2b-mcp-no-arguments]'), 'No arguments: a dash, not "[]".');
        self::assertStringNotContainsString('tmonier_b2b_mcp.', $content, 'Every label is translated.');
    }

    public function testTheShopAccountMenuLinksToTheAssistantsPage(): void
    {
        $this->loginShop($this->customer());

        $crawler = $this->client->request('GET', '/en_US/account/dashboard');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('a[href="/en_US/account/ai-assistants"]'));
    }

    private function loginAdmin(): void
    {
        /** @var FactoryInterface<AdminUserInterface> $factory */
        $factory = $this->service('sylius.factory.admin_user');
        $admin = $factory->createNew();
        $admin->setUsername('mcp-admin-' . bin2hex(random_bytes(4)));
        $admin->setEmail($admin->getUsername() . '@example.com');
        $admin->setPlainPassword('sylius');
        $admin->setLocaleCode('en_US');
        $admin->setEnabled(true);
        $this->entityManager()->persist($admin);
        $this->entityManager()->flush();
        $this->client->loginUser($admin, 'admin');
    }
}
