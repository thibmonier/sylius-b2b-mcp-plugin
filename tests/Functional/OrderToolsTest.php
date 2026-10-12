<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Functional;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Scopes;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyMemberInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Factory\CompanyMemberFactoryInterface;

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
        $response = $this->rpc($this->accessToken($this->customer()), 'tools/list');
        $tools = self::indexBy($response, 'name', 'result', 'tools');
        $names = array_keys($tools);

        foreach (['list_my_orders', 'get_order', 'reorder', 'get_checkout_link', 'quick_order_add', 'request_quote', 'list_my_quotes', 'get_trade_credit_statement', 'search_products', 'fetch_product'] as $name) {
            self::assertContains($name, $names);
        }
        // The guest checkout of the official server is hidden: checkout happens on the shop.
        foreach (['complete_checkout', 'create_order', 'select_payment_method', 'update_order_address'] as $name) {
            self::assertNotContains($name, $names);
        }
        self::assertSame(['order_number', 'idempotency_key'], self::at($tools, 'reorder', 'inputSchema', 'required'));
        self::assertFalse(self::at($tools, 'reorder', 'annotations', 'readOnlyHint'));
        self::assertTrue(self::at($tools, 'list_my_orders', 'annotations', 'readOnlyHint'));
    }

    public function testACustomerOnlySeesTheirOwnOrders(): void
    {
        $customer = $this->customer();
        $own = $this->order($customer);
        $other = $this->order($this->customer('Eve', 'Other'));
        $token = $this->accessToken($customer);

        $list = $this->callTool($token, 'list_my_orders');
        self::assertSame(1, $list['total']);
        self::assertSame($own->getNumber(), self::at($list, 'orders', 0, 'number'));
        self::assertSame('55.00', self::at($list, 'orders', 0, 'total'));
        self::assertArrayNotHasKey('placed_by', self::arrayAt($list, 'orders', 0));

        $detail = $this->callTool($token, 'get_order', ['order_number' => (string) $own->getNumber()]);
        self::assertSame($own->getNumber(), $detail['number']);
        self::assertSame(2, self::at($detail, 'items', 0, 'quantity'));
        self::assertSame('25.00', self::at($detail, 'items', 0, 'unit_price'));
        self::assertSame('Springfield', self::at($detail, 'shipping_address', 'city'));

        $foreign = $this->callTool($token, 'get_order', ['order_number' => (string) $other->getNumber()]);
        self::assertSame(sprintf('No order "%s" visible to you on this shop.', $other->getNumber()), $foreign['error']);
        $unknown = self::stringAt($this->callTool($token, 'get_order', ['order_number' => '999999999']), 'error');
        self::assertSame($foreign['error'], str_replace('999999999', (string) $other->getNumber(), $unknown), 'Same answer for unknown and forbidden orders.');
    }

    public function testOrdersOfAnotherChannelAndCartsAreNotVisible(): void
    {
        $customer = $this->customer();
        $elsewhere = $this->order($customer);
        $elsewhere->setChannel($this->otherChannel());
        $token = $this->accessToken($customer);
        $this->callTool($token, 'reorder', ['order_number' => (string) $this->order($customer)->getNumber(), 'idempotency_key' => 'fill-cart-01']);
        $cart = $this->cartOf($customer);
        self::assertNotNull($cart);
        $cart->setNumber('CART-' . bin2hex(random_bytes(3)));
        $this->entityManager()->flush();

        self::assertArrayHasKey('error', $this->callTool($token, 'get_order', ['order_number' => (string) $elsewhere->getNumber()]));
        self::assertArrayHasKey('error', $this->callTool($token, 'get_order', ['order_number' => (string) $cart->getNumber()]));
        self::assertSame(1, $this->callTool($token, 'list_my_orders')['total']);
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
        self::assertCount(2, self::arrayAt($page, 'orders'));
        self::assertSame($newest->getNumber(), self::at($page, 'orders', 0, 'number'));
        self::assertCount(1, self::arrayAt($this->callTool($token, 'list_my_orders', ['limit' => 2, 'page' => 2]), 'orders'));
    }

    public function testCompanyOrdersFollowTheRolesOfTheCompanyAccountsPlugin(): void
    {
        $admin = $this->customer('Alice', 'Admin');
        $buyer = $this->customer('Bob', 'Buyer');
        $this->company([$admin, CompanyMemberInterface::ROLE_ADMIN], [$buyer, CompanyMemberInterface::ROLE_BUYER]);
        $buyerOrder = $this->order($buyer);

        $adminToken = $this->accessToken($admin);
        $companyOrders = $this->callTool($adminToken, 'list_my_orders', ['scope' => 'company']);
        self::assertSame([$buyerOrder->getNumber()], self::column($companyOrders, 'number', 'orders'));
        self::assertSame('Bob Buyer', self::at($companyOrders, 'orders', 0, 'placed_by'));
        self::assertSame(0, $this->callTool($adminToken, 'list_my_orders')['total']);
        $detail = $this->callTool($adminToken, 'get_order', ['order_number' => (string) $buyerOrder->getNumber()]);
        self::assertSame('Bob Buyer', $detail['placed_by']);

        $this->mcpSessionId = null;
        $buyerToken = $this->accessToken($buyer);
        self::assertSame('Your company role does not give access to the company orders.', $this->callTool($buyerToken, 'list_my_orders', ['scope' => 'company'])['error']);
        $adminOrder = $this->order($admin);
        self::assertArrayHasKey('error', $this->callTool($buyerToken, 'get_order', ['order_number' => (string) $adminOrder->getNumber()]), 'A buyer does not see the orders of colleagues.');

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
        $lines = self::indexBy($result, 'sku', 'lines');
        $kept = (string) $kept->getCode();
        $repriced = (string) $repriced->getCode();
        $withdrawn = (string) $withdrawn->getCode();
        self::assertSame('added', self::at($lines, $kept, 'status'));
        self::assertFalse(self::at($lines, $kept, 'price_changed'));
        self::assertSame('added', self::at($lines, $repriced, 'status'));
        self::assertTrue(self::at($lines, $repriced, 'price_changed'));
        self::assertSame('10.00', self::at($lines, $repriced, 'previous_unit_price'));
        self::assertSame('12.00', self::at($lines, $repriced, 'current_unit_price'));
        self::assertSame('disabled', self::at($lines, $withdrawn, 'status'));
        self::assertSame(0, self::at($lines, $withdrawn, 'added_quantity'));
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

        self::assertStringContainsString('was not granted the "b2b:cart" permission', self::stringAt($result, 'error'));
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
        self::assertSame(2, self::at($link, 'cart', 'items_quantity'));

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
