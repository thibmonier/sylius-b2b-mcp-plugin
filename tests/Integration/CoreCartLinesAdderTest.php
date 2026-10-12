<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Integration;

use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Tmonier\SyliusB2bMcpPlugin\Functional\B2bMcpTestTrait;
use Tmonier\SyliusB2bMcpPlugin\Cart\CartLine;
use Tmonier\SyliusB2bMcpPlugin\Cart\CartLineResult;
use Tmonier\SyliusB2bMcpPlugin\Cart\CoreCartLinesAdder;
use Tmonier\SyliusB2bMcpPlugin\Cart\CustomerCartProvider;
use Tmonier\SyliusB2bMcpPlugin\Security\AuthenticatedConnection;

/** The cart adder used without tmonier/sylius-quick-order-plugin (core Sylius rules). */
final class CoreCartLinesAdderTest extends WebTestCase
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

    public function testEachLineIsCheckedLikeTheShopAddToCart(): void
    {
        $valid = $this->variant(1000);
        $disabled = $this->variant();
        $disabled->setEnabled(false);
        $elsewhere = $this->variant();
        $product = $elsewhere->getProduct();
        self::assertInstanceOf(\Sylius\Component\Core\Model\ProductInterface::class, $product);
        $product->removeChannel($this->channel());
        $unpriced = $this->variant(1000, 'ANOTHER_CHANNEL');
        $scarce = $this->variant();
        $scarce->setTracked(true);
        $scarce->setOnHand(5);
        $scarce->setOnHold(2);
        $this->entityManager()->flush();
        $cart = $this->cart();

        $result = $this->adder()->add($cart, [
            new CartLine((string) $valid->getCode(), 2),
            new CartLine((string) $valid->getCode(), 1),
            new CartLine((string) $disabled->getCode(), 1),
            new CartLine((string) $elsewhere->getCode(), 1),
            new CartLine((string) $unpriced->getCode(), 1),
            new CartLine((string) $scarce->getCode(), 4),
            new CartLine('UNKNOWN', 1),
        ]);

        self::assertTrue($result->applied);
        self::assertSame(
            [CartLineResult::ADDED, CartLineResult::DISABLED, CartLineResult::NOT_IN_CHANNEL, CartLineResult::NO_CHANNEL_PRICING, CartLineResult::INSUFFICIENT_STOCK, CartLineResult::UNKNOWN_CODE],
            array_map(static fn (CartLineResult $line): string => $line->status, $result->lines),
        );
        self::assertSame(3, $result->lines[0]->addedQuantity, 'Lines with the same code are merged.');
        self::assertSame(3, $result->lines[4]->available);
        self::assertSame(3, $cart->getTotalQuantity());
        self::assertNotNull($cart->getId(), 'The new cart is persisted.');
    }

    public function testAllOrNothingAndPreviewChangeNothing(): void
    {
        $valid = $this->variant();
        $cart = $this->cart();

        $preview = $this->adder()->preview($cart, [new CartLine((string) $valid->getCode(), 1)]);
        self::assertSame(CartLineResult::VALID, $preview->lines[0]->status);
        self::assertSame(0, $cart->getTotalQuantity());

        $refused = $this->adder()->add($cart, [new CartLine((string) $valid->getCode(), 1), new CartLine('UNKNOWN', 1)], true);
        self::assertFalse($refused->applied);
        self::assertSame(CartLineResult::SKIPPED, $refused->lines[0]->status);
        self::assertSame(0, $cart->getTotalQuantity());
    }

    public function testQuantitiesAddUpWithTheCartAndTheShopLimit(): void
    {
        $variant = $this->variant();
        $cart = $this->cart();
        $this->adder()->add($cart, [new CartLine((string) $variant->getCode(), 2)]);

        $again = $this->adder()->add($cart, [new CartLine((string) $variant->getCode(), 3)]);
        self::assertSame(CartLineResult::ADDED, $again->lines[0]->status);
        self::assertCount(1, $cart->getItems());
        self::assertSame(5, $cart->getTotalQuantity());

        $limit = self::getContainer()->getParameter('sylius.order_item_quantity_modifier.limit');
        self::assertIsInt($limit);
        $tooMany = $this->adder()->add($cart, [new CartLine((string) $variant->getCode(), $limit)]);
        self::assertSame(CartLineResult::QUANTITY_LIMIT_EXCEEDED, $tooMany->lines[0]->status);
        self::assertSame(5, $cart->getTotalQuantity());
    }

    private function cart(): OrderInterface
    {
        $customer = $this->customer();
        $user = $customer->getUser();
        self::assertInstanceOf(\Sylius\Component\Core\Model\ShopUserInterface::class, $user);
        $provider = $this->service('tmonier_b2b_mcp.cart.provider');
        self::assertInstanceOf(CustomerCartProvider::class, $provider);

        return $provider->getOrCreate(new AuthenticatedConnection(new \Tmonier\SyliusB2bMcpPlugin\Entity\Connection(), $user, $customer, $this->channel(), []));
    }

    private function adder(): CoreCartLinesAdder
    {
        $adder = $this->service('tests.tmonier_b2b_mcp.cart.core_adder');
        self::assertInstanceOf(CoreCartLinesAdder::class, $adder);

        return $adder;
    }
}
