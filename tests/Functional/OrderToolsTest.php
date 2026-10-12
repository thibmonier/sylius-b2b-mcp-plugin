<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Functional;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Scopes;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Factory\CompanyMemberFactoryInterface;
use Sylius\Resource\Factory\FactoryInterface;

/** list_my_orders, get_order, reorder and get_checkout_link through the MCP endpoint. */
final class OrderToolsTest extends WebTestCase
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

    public function testTheToolsAreListedNextToTheCatalogToolsOfTheOfficialServer(): void
    {
        $tools = $this->rpc($this->accessToken($this->customer()), 'tools/list')['result']['tools'] ?? null;
        self::assertIsArray($tools);
        $names = array_column($tools, 'name');

        foreach (['list_my_orders', 'get_order', 'reorder', 'get_checkout_link', 'quick_order_add', 'request_quote', 'list_my_quotes', 'get_trade_credit_statement', 'search_products', 'fetch_product'] as $name) {
            self::assertContains($name, $names);
        }
        // The guest checkout of the official server is hidden: checkout happens on the shop.
        foreach (['complete_checkout', 'create_order', 'select_payment_method', 'update_order_address'] as $name) {
            self::assertNotContains($name, $names);
        }
        $reorder = $tools[array_search('reorder', $names, true)];
        self::assertSame(['order_number', 'idempotency_key'], $reorder['inputSchema']['required']);
        self::assertFalse($reorder['annotations']['readOnlyHint']);
        self::assertTrue($tools[array_search('list_my_orders', $names, true)]['annotations']['readOnlyHint']);
    }

    public function testACustomerOnlySeesTheirOwnOrders(): void
    {
        $customer = $this->customer();
        $own = $this->order($customer);
        $other = $this->order($this->customer('Eve', 'Other'));
        $token = $this->accessToken($customer);

        $list = $this->callTool($token, 'list_my_orders');
        self::assertSame(1, $list['total']);
        self::assertSame($own->getNumber(), $list['orders'][0]['number']);
        self::assertSame('55.00', $list['orders'][0]['total']);
        self::assertArrayNotHasKey('placed_by', $list['orders'][0]);

        $detail = $this->callTool($token, 'get_order', ['order_number' => (string) $own->getNumber()]);
        self::assertSame($own->getNumber(), $detail['number']);
        self::assertSame(2, $detail['items'][0]['quantity']);
        self::assertSame('25.00', $detail['items'][0]['unit_price']);
        self::assertSame('Springfield', $detail['shipping_address']['city']);

        $foreign = $this->callTool($token, 'get_order', ['order_number' => (string) $other->getNumber()]);
        self::assertSame(sprintf('No order "%s" visible to you on this shop.', $other->getNumber()), $foreign['error']);
        self::assertSame($foreign['error'], str_replace('999999999', (string) $other->getNumber(), $this->callTool($token, 'get_order', ['order_number' => '999999999'])['error']));
    }

    public function testPagination(): void
    {
        $customer = $this->customer();
        $this->order($customer);
        $this->order($customer);
        $newest = $this->order($customer);
        $token = $this->accessToken($customer);

        $page = $this->callTool($token, 'list_my_orders', ['limit' => 2, 'page' => 1]);
        self::assertSame(3, $page['total']);
        self::assertSame(2, $page['pages']);
        self::assertCount(2, $page['orders']);
        self::assertSame($newest->getNumber(), $page['orders'][0]['number']);
        self::assertCount(1, $this->callTool($token, 'list_my_orders', ['limit' => 2, 'page' => 2])['orders']);
    }

    public function testCompanyOrdersFollowTheRolesOfTheCompanyAccountsPlugin(): void
    {
        $admin = $this->customer('Alice', 'Admin');
        $buyer = $this->customer('Bob', 'Buyer');
        $this->company([$admin, CompanyMemberInterface::ROLE_ADMIN], [$buyer, CompanyMemberInterface::ROLE_BUYER]);
        $buyerOrder = $this->order($buyer);

        $adminToken = $this->accessToken($admin);
        $companyOrders = $this->callTool($adminToken, 'list_my_orders', ['scope' => 'company']);
        self::assertSame([$buyerOrder->getNumber()], array_column($companyOrders['orders'], 'number'));
        self::assertSame('Bob Buyer', $companyOrders['orders'][0]['placed_by']);
        self::assertSame(0, $this->callTool($adminToken, 'list_my_orders')['total']);
        $detail = $this->callTool($adminToken, 'get_order', ['order_number' => (string) $buyerOrder->getNumber()]);
        self::assertSame('Bob Buyer', $detail['placed_by']);

        $this->mcpSessionId = null;
        $buyerToken = $this->accessToken($buyer);
        self::assertSame('Your company role does not give access to the company orders.', $this->callTool($buyerToken, 'list_my_orders', ['scope' => 'company'])['error']);

        $this->mcpSessionId = null;
        $outsiderToken = $this->accessToken($this->customer('Eve', 'Other'));
        self::assertArrayHasKey('error', $this->callTool($outsiderToken, 'get_order', ['order_number' => (string) $buyerOrder->getNumber()]));
        self::assertArrayHasKey('error', $this->callTool($outsiderToken, 'list_my_orders', ['scope' => 'company']));
    }

    public function testReorderAddsTheItemsAtTodaysPricesAndReportsWhatChanged(): void
    {
        $customer = $this->customer();
        $kept = $this->variant(2500);
        $repriced = $this->variant(1000);
        $withdrawn = $this->variant(700);
        $order = $this->order($customer, $kept, $repriced, $withdrawn);
        $repriced->getChannelPricingForChannel($this->channel())?->setPrice(1200);
        $withdrawn->setEnabled(false);
        $this->entityManager()->flush();
        $token = $this->accessToken($customer);

        $result = $this->callTool($token, 'reorder', ['order_number' => (string) $order->getNumber(), 'idempotency_key' => 'reorder-0001']);

        self::assertTrue($result['applied']);
        $lines = array_column($result['lines'], null, 'sku');
        self::assertSame('added', $lines[$kept->getCode()]['status']);
        self::assertFalse($lines[$kept->getCode()]['price_changed']);
        self::assertSame('added', $lines[$repriced->getCode()]['status']);
        self::assertTrue($lines[$repriced->getCode()]['price_changed']);
        self::assertSame('10.00', $lines[$repriced->getCode()]['previous_unit_price']);
        self::assertSame('12.00', $lines[$repriced->getCode()]['current_unit_price']);
        self::assertSame('disabled', $lines[$withdrawn->getCode()]['status']);
        self::assertSame(0, $lines[$withdrawn->getCode()]['added_quantity']);
        self::assertSame('http://localhost/en_US/cart/', $result['checkout_url']);

        $cart = $this->cartOf($customer);
        self::assertNotNull($cart);
        self::assertSame(4, $cart->getTotalQuantity());
        self::assertSame('cart', $cart->getState());
        self::assertSame('new', $this->entityManager()->find(\Sylius\Component\Core\Model\Order::class, $order->getId())?->getState(), 'The past order is untouched.');
    }

    public function testReorderIsIdempotent(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer);
        $token = $this->accessToken($customer);
        $arguments = ['order_number' => (string) $order->getNumber(), 'idempotency_key' => 'same-key-123'];

        $first = $this->callTool($token, 'reorder', $arguments);
        $retry = $this->callTool($token, 'reorder', $arguments);

        self::assertArrayNotHasKey('replayed', $first);
        self::assertTrue($retry['replayed']);
        self::assertSame($first['lines'], $retry['lines']);
        self::assertSame(2, $this->cartOf($customer)?->getTotalQuantity());

        $other = $this->order($customer);
        $conflict = $this->callTool($token, 'reorder', ['order_number' => (string) $other->getNumber(), 'idempotency_key' => 'same-key-123']);
        self::assertSame('This idempotency key was already used with other arguments. Use a new key for a new request.', $conflict['error']);

        self::assertArrayHasKey('error', $this->callTool($token, 'reorder', ['order_number' => (string) $other->getNumber(), 'idempotency_key' => 'bad key!']));
        self::assertSame(2, $this->cartOf($customer)?->getTotalQuantity());
    }

    public function testCartChangesNeedTheCartScope(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer);
        $token = $this->accessToken($customer, [Scopes::READ]);

        $result = $this->callTool($token, 'reorder', ['order_number' => (string) $order->getNumber(), 'idempotency_key' => 'scope-check-1']);

        self::assertStringContainsString('was not granted the "b2b:cart" permission', $result['error']);
        self::assertNull($this->cartOf($customer));
        self::assertSame(1, $this->callTool($token, 'list_my_orders')['total']);
    }

    public function testTheCheckoutLinkHandsTheCartOverToTheShop(): void
    {
        $customer = $this->customer();
        $token = $this->accessToken($customer);

        $empty = $this->callTool($token, 'get_checkout_link');
        self::assertNull($empty['cart']);
        self::assertSame('The cart is empty.', $empty['note']);

        $this->callTool($token, 'reorder', ['order_number' => (string) $this->order($customer)->getNumber(), 'idempotency_key' => 'checkout-link']);
        $link = $this->callTool($token, 'get_checkout_link');
        self::assertSame('http://localhost/en_US/cart/', $link['checkout_url']);
        self::assertSame(2, $link['cart']['items_quantity']);

        // The shop shows the same cart once the customer signs in.
        $this->client->getCookieJar()->clear();
        $this->loginShop($customer);
        $this->client->request('GET', '/en_US/cart/');
        self::assertResponseIsSuccessful();
        $item = $this->cartOf($customer)?->getItems()->first();
        self::assertInstanceOf(OrderItemInterface::class, $item);
        self::assertStringContainsString((string) $item->getVariant()?->getCode(), (string) $this->client->getResponse()->getContent());
    }

    public function testEveryCallIsAudited(): void
    {
        $customer = $this->customer();
        $token = $this->accessToken($customer);
        $this->callTool($token, 'list_my_orders', ['limit' => 3]);
        $this->callTool($token, 'get_order', ['order_number' => 'nope']);

        /** @var list<array{tool: string, outcome: string, customer_email: string, client_name: string, arguments: string}> $rows */
        $rows = $this->connection()->fetchAllAssociative('SELECT tool, outcome, customer_email, client_name, arguments FROM tmonier_b2b_mcp_tool_call WHERE customer_email = ? ORDER BY id', [$customer->getEmail()]);
        self::assertSame(['list_my_orders', 'get_order'], array_column($rows, 'tool'));
        self::assertSame(['success', 'refused'], array_column($rows, 'outcome'));
        self::assertSame('Claude', $rows[0]['client_name']);
        self::assertSame('{"scope":"mine","page":1,"limit":3}', $rows[0]['arguments']);
    }

    /** @param array{CustomerInterface, string} ...$members */
    private function company(array ...$members): CompanyInterface
    {
        /** @var FactoryInterface<CompanyInterface> $factory */
        $factory = $this->service('tmonier_company_accounts.factory.company');
        $company = $factory->createNew();
        $company->setCode('MCP_' . strtoupper(bin2hex(random_bytes(4))));
        $company->setName('Acme Industries');
        $this->entityManager()->persist($company);
        $this->entityManager()->flush();
        /** @var CompanyMemberFactoryInterface $memberFactory */
        $memberFactory = $this->service('tmonier_company_accounts.factory.company_member');
        foreach ($members as [$customer, $role]) {
            $customer = $this->managed($customer);
            $this->entityManager()->persist($memberFactory->createForCustomer($company, $customer, $role));
        }
        $this->entityManager()->flush();

        return $company;
    }
}
