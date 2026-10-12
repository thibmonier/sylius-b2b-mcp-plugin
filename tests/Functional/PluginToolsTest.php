<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Functional;

use Sylius\Component\Core\Factory\PaymentMethodFactoryInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusTradeCreditPlugin\Account\PaymentTermsPolicyProvider;
use Tmonier\SyliusTradeCreditPlugin\Entity\PaymentTermsPolicyInterface;
use Tmonier\SyliusTradeCreditPlugin\Factory\TradeCreditAccountFactory;
use Tmonier\SyliusTradeCreditPlugin\Terms\EndOfMonthMode;

/** Tools of the optional plugins: quick order, quote request, trade credit. */
final class PluginToolsTest extends WebTestCase
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

    public function testQuickOrderPreviewsThenAdds(): void
    {
        $customer = $this->customer();
        $variant = $this->variant(1500);
        $token = $this->accessToken($customer);
        $lines = [['sku' => (string) $variant->getCode(), 'quantity' => 3], ['sku' => 'UNKNOWN-SKU', 'quantity' => 1]];

        $preview = $this->callTool($token, 'quick_order_add', ['lines' => $lines, 'mode' => 'partial']);
        self::assertTrue($preview['preview']);
        self::assertFalse($preview['applied']);
        self::assertSame(['valid', 'unknown_code'], self::column($preview, 'status', 'lines'));
        self::assertNull($this->cartOf($customer));

        $missingKey = $this->callTool($token, 'quick_order_add', ['lines' => $lines, 'confirm' => true, 'mode' => 'partial']);
        self::assertArrayHasKey('error', $missingKey);
        self::assertNull($this->cartOf($customer));

        $added = $this->callTool($token, 'quick_order_add', ['lines' => $lines, 'confirm' => true, 'mode' => 'partial', 'idempotency_key' => 'quick-order-1']);
        self::assertTrue($added['applied']);
        self::assertSame(['added', 'unknown_code'], self::column($added, 'status', 'lines'));
        self::assertSame(3, self::at($added, 'cart', 'items_quantity'));
        self::assertSame(3, $this->cartOf($customer)?->getTotalQuantity());
    }

    public function testQuickOrderAllOrNothingAddsNothingWhenALineFails(): void
    {
        $customer = $this->customer();
        $variant = $this->variant();
        $token = $this->accessToken($customer);

        $result = $this->callTool($token, 'quick_order_add', [
            'lines' => [['sku' => (string) $variant->getCode(), 'quantity' => 1], ['sku' => 'UNKNOWN-SKU', 'quantity' => 1]],
            'confirm' => true,
            'mode' => 'all_or_nothing',
            'idempotency_key' => 'quick-order-2',
        ]);

        self::assertFalse($result['applied']);
        self::assertSame(['skipped', 'unknown_code'], self::column($result, 'status', 'lines'));
        self::assertNull($this->cartOf($customer));
    }

    public function testAQuoteRequestFromLinesIsListedWithItsStatus(): void
    {
        $customer = $this->customer();
        $variant = $this->variant(4000);
        $token = $this->accessToken($customer);

        $quote = $this->callTool($token, 'request_quote', [
            'source' => 'lines',
            'lines' => [['sku' => (string) $variant->getCode(), 'quantity' => 50], ['sku' => 'UNKNOWN-SKU', 'quantity' => 1]],
            'message' => 'Volume price for 50 units?',
            'idempotency_key' => 'quote-request-1',
        ]);

        $number = self::stringAt($quote, 'number');
        self::assertSame('new', $quote['status']);
        self::assertSame(50, self::at($quote, 'lines', 0, 'quantity'));
        self::assertSame([['sku' => 'UNKNOWN-SKU', 'quantity' => 1, 'reason' => 'not_quotable']], $quote['skipped']);
        self::assertStringEndsWith('/en_US/account/quotes/' . $number, self::stringAt($quote, 'url'));

        $retry = $this->callTool($token, 'request_quote', [
            'source' => 'lines',
            'lines' => [['sku' => (string) $variant->getCode(), 'quantity' => 50], ['sku' => 'UNKNOWN-SKU', 'quantity' => 1]],
            'message' => 'Volume price for 50 units?',
            'idempotency_key' => 'quote-request-1',
        ]);
        self::assertSame($number, $retry['number']);

        $list = $this->callTool($token, 'list_my_quotes');
        self::assertSame(1, $list['total']);
        self::assertSame($number, self::at($list, 'quotes', 0, 'number'));
        self::assertNull(self::at($list, 'quotes', 0, 'lines', 0, 'unit_price'), 'No merchant price before a proposal.');
        self::assertSame('Volume price for 50 units?', $this->sqlValue('SELECT m.body FROM tmonier_quote_request_message m INNER JOIN tmonier_quote_request q ON q.id = m.quote_request_id WHERE q.number = ?', [$number]));
    }

    public function testAQuoteRequestFromTheCart(): void
    {
        $customer = $this->customer();
        $token = $this->accessToken($customer);
        self::assertSame('The cart is empty.', $this->callTool($token, 'request_quote', ['idempotency_key' => 'quote-empty-1'])['error']);

        $this->callTool($token, 'reorder', ['order_number' => (string) $this->order($customer)->getNumber(), 'idempotency_key' => 'fill-the-cart']);
        $quote = $this->callTool($token, 'request_quote', ['source' => 'cart', 'idempotency_key' => 'quote-cart-1']);

        self::assertSame(2, self::at($quote, 'lines', 0, 'quantity'));
        self::assertSame($this->cartOf($customer)?->getId(), $this->sqlInt('SELECT source_order_id FROM tmonier_quote_request WHERE number = ?', [self::stringAt($quote, 'number')]));
    }

    public function testAQuoteRequestNeedsTheNameOfTheCustomer(): void
    {
        $token = $this->accessToken($this->customer('', ''));

        $result = $this->callTool($token, 'request_quote', ['source' => 'lines', 'lines' => [['sku' => (string) $this->variant()->getCode(), 'quantity' => 1]], 'idempotency_key' => 'quote-noname']);

        self::assertSame('Add your first and last name to your shop account before requesting a quote.', $result['error']);
    }

    public function testQuotesOfOtherCustomersAreNotListed(): void
    {
        $this->callTool($this->accessToken($this->customer()), 'request_quote', ['source' => 'lines', 'lines' => [['sku' => (string) $this->variant()->getCode(), 'quantity' => 1]], 'idempotency_key' => 'quote-other']);
        $this->mcpSessionId = null;

        self::assertSame(0, $this->callTool($this->accessToken($this->customer('Eve', 'Other')), 'list_my_quotes')['total']);
    }

    public function testTheTradeCreditStatementShowsLimitOpenItemsAndAgeing(): void
    {
        $customer = $this->customer();
        $token = $this->accessToken($customer);
        self::assertSame('No trade credit account: payment on account is not available for you on this shop.', $this->callTool($token, 'get_trade_credit_statement')['error']);

        $method = $this->onAccountMethod();
        $this->account($customer, 100000);
        $current = $this->orderPaidWith($method, $customer);
        $late = $this->orderPaidWith($method, $customer);
        $this->connection()->executeStatement('UPDATE tmonier_trade_credit_order_terms SET due_date = ? WHERE order_id = ?', [(new \DateTimeImmutable('-45 days'))->format('Y-m-d'), $late->getId()]);

        $statement = $this->callTool($token, 'get_trade_credit_statement');

        self::assertSame('1000.00', $statement['credit_limit']);
        self::assertSame('110.00', $statement['committed']);
        self::assertSame('890.00', $statement['available']);
        self::assertFalse($statement['on_hold']);
        self::assertSame('55.00', $statement['overdue_amount']);
        self::assertSame(['not_due' => '55.00', 'overdue_1_30' => '0.00', 'overdue_31_60' => '55.00', 'overdue_61_90' => '0.00', 'overdue_over_90' => '0.00'], $statement['ageing']);
        $items = self::indexBy($statement, 'order_number', 'open_items');
        self::assertFalse(self::at($items, (string) $current->getNumber(), 'overdue'));
        self::assertTrue(self::at($items, (string) $late->getNumber(), 'overdue'));
        self::assertTrue(self::at($items, (string) $late->getNumber(), 'placed_by_you'));
    }

    private function onAccountMethod(): PaymentMethodInterface
    {
        /** @var PaymentMethodFactoryInterface<PaymentMethodInterface> $factory */
        $factory = $this->service('sylius.factory.payment_method');
        $method = $factory->createWithGateway('offline');
        $code = 'mcp_on_account_' . bin2hex(random_bytes(4));
        $method->setCode($code);
        $method->getGatewayConfig()?->setGatewayName($code);
        $method->setCurrentLocale($this->localeCode());
        $method->setFallbackLocale($this->localeCode());
        $method->setName('On account ' . $code);
        $method->addChannel($this->channel());
        $method->setEnabled(true);
        $this->entityManager()->persist($method);

        /** @var FactoryInterface<PaymentTermsPolicyInterface> $policyFactory */
        $policyFactory = $this->service('tmonier_trade_credit.factory.payment_terms_policy');
        $policy = $policyFactory->createNew();
        $policy->setPaymentMethod($method);
        $policy->setNetDays(30);
        $policy->setEndOfMonthMode(EndOfMonthMode::None);
        $this->entityManager()->persist($policy);
        $this->entityManager()->flush();
        $provider = $this->service('tmonier_trade_credit.provider.payment_terms_policy');
        self::assertInstanceOf(PaymentTermsPolicyProvider::class, $provider);
        $provider->reset();

        return $method;
    }

    private function account(CustomerInterface $customer, int $limit): void
    {
        $customer = $this->managed($customer);
        /** @var TradeCreditAccountFactory $factory */
        $factory = $this->service('tmonier_trade_credit.factory.account');
        $customerId = $customer->getId();
        self::assertIsInt($customerId);
        $account = $factory->createForCustomer($customerId);
        $account->setCurrencyCode($this->channel()->getBaseCurrency()?->getCode());
        $account->setCreditLimit($limit);
        $this->entityManager()->persist($account);
        $this->entityManager()->flush();
    }
}
