<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Addressing\Model\CountryInterface;
use Sylius\Component\Addressing\Model\ZoneInterface;
use Sylius\Component\Addressing\Model\ZoneMemberInterface;
use Sylius\Component\Core\Factory\PaymentMethodFactoryInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\ConnectionInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\OAuthToken;
use Tmonier\SyliusB2bMcpPlugin\OAuth\ClientRegistrar;
use Tmonier\SyliusB2bMcpPlugin\OAuth\ConnectionManager;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Scopes;
use Tmonier\SyliusB2bMcpPlugin\OAuth\TokenStore;

/**
 * Tests run against the test application loaded with the (random) Sylius fixtures, each one in a
 * transaction rolled back at the end. They create the data they need: zone, shipping and payment
 * methods, products, customers, orders, OAuth clients and connections.
 */
trait B2bMcpTestTrait
{
    private KernelBrowser $client;

    private ?ShippingMethodInterface $shippingMethod = null;

    private ?PaymentMethodInterface $paymentMethod = null;

    private ?string $mcpSessionId = null;

    private int $rpcId = 0;

    abstract protected static function getContainer(): ContainerInterface;

    private function setUpClient(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $pool = self::getContainer()->get('cache.tmonier_b2b_mcp_limiter');
        self::assertInstanceOf(AdapterInterface::class, $pool);
        $pool->clear();
        $this->connection()->beginTransaction();
    }

    private function tearDownClient(): void
    {
        if ($this->connection()->isTransactionActive()) {
            $this->connection()->rollBack();
        }
    }

    // MCP over HTTP

    /** An access token of a new connection of the customer (OAuth flow skipped). */
    private function accessToken(CustomerInterface $customer, array $scopes = Scopes::ALL, ?string $channelCode = null, ?ConnectionInterface &$connection = null): string
    {
        $this->entityManager()->flush();
        /** @var ClientRegistrar $registrar */
        $registrar = $this->service('tmonier_b2b_mcp.oauth.client_registrar');
        [$oauthClient] = $registrar->create('Claude', ['https://claude.ai/api/mcp/auth_callback'], false);
        /** @var ConnectionManager $manager */
        $manager = $this->service('tmonier_b2b_mcp.oauth.connection_manager');
        $connection = $manager->approve($customer, $oauthClient, $channelCode ?? (string) $this->channel()->getCode(), $scopes);
        $token = bin2hex(random_bytes(40));
        /** @var TokenStore $store */
        $store = $this->service('tmonier_b2b_mcp.oauth.token_store');
        $store->store(OAuthToken::TYPE_ACCESS, $token, $connection, $scopes, new \DateTimeImmutable('+1 hour'));

        return $token;
    }

    /**
     * Sends a JSON-RPC request to the MCP endpoint (initializing the MCP session first).
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed> the JSON-RPC response
     */
    private function rpc(string $token, string $method, array $params = []): array
    {
        if (null === $this->mcpSessionId) {
            $initialize = $this->post($token, ['jsonrpc' => '2.0', 'id' => ++$this->rpcId, 'method' => 'initialize', 'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
            ]]);
            self::assertSame(200, $this->client->getResponse()->getStatusCode(), (string) $this->client->getResponse()->getContent());
            self::assertArrayHasKey('result', $initialize);
            $this->mcpSessionId = (string) $this->client->getResponse()->headers->get('Mcp-Session-Id');
            $this->post($token, ['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);
        }

        return $this->post($token, ['jsonrpc' => '2.0', 'id' => ++$this->rpcId, 'method' => $method, 'params' => [] === $params ? new \stdClass() : $params]);
    }

    /**
     * Calls a tool; returns the structured result, or the error text under "error".
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function callTool(string $token, string $name, array $arguments = []): array
    {
        $response = $this->rpc($token, 'tools/call', ['name' => $name, 'arguments' => [] === $arguments ? new \stdClass() : $arguments]);
        self::assertArrayHasKey('result', $response, json_encode($response, \JSON_THROW_ON_ERROR));
        $result = $response['result'];
        self::assertIsArray($result);
        if (true === ($result['isError'] ?? false)) {
            $content = $result['content'] ?? [];
            self::assertIsArray($content);
            $first = $content[0] ?? [];
            self::assertIsArray($first);

            return ['error' => (string) ($first['text'] ?? '')];
        }
        $structured = $result['structuredContent'] ?? null;
        self::assertIsArray($structured, json_encode($result, \JSON_THROW_ON_ERROR));

        /** @var array<string, mixed> $structured */
        return $structured;
    }

    /**
     * @param array<string, mixed> $message
     *
     * @return array<string, mixed>
     */
    private function post(string $token, array $message): array
    {
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ];
        if (null !== $this->mcpSessionId) {
            $headers['HTTP_MCP_SESSION_ID'] = $this->mcpSessionId;
        }
        $this->client->request('POST', '/_mcp', [], [], $headers, json_encode($message, \JSON_THROW_ON_ERROR));
        $content = (string) $this->client->getResponse()->getContent();
        if ('' === $content) {
            return [];
        }
        $decoded = json_decode($content, true);
        self::assertIsArray($decoded, $content);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    // Shop data

    private function channel(): ChannelInterface
    {
        $channel = $this->entityManager()->getRepository(ChannelInterface::class)->findOneBy(['hostname' => 'localhost']);
        if (!$channel instanceof ChannelInterface) {
            $channel = $this->entityManager()->getRepository(ChannelInterface::class)->findOneBy(['enabled' => true]);
        }
        self::assertInstanceOf(ChannelInterface::class, $channel);

        return $channel;
    }

    private function localeCode(): string
    {
        return (string) $this->channel()->getDefaultLocale()?->getCode();
    }

    private function customer(string $firstName = 'Ada', string $lastName = 'Buyer'): CustomerInterface
    {
        /** @var FactoryInterface<CustomerInterface> $factory */
        $factory = $this->service('sylius.factory.customer');
        $customer = $factory->createNew();
        $customer->setEmail(sprintf('mcp-%s@example.com', bin2hex(random_bytes(4))));
        $customer->setFirstName($firstName);
        $customer->setLastName($lastName);
        /** @var FactoryInterface<ShopUserInterface> $userFactory */
        $userFactory = $this->service('sylius.factory.shop_user');
        $user = $userFactory->createNew();
        $user->setPlainPassword('sylius');
        $user->setEnabled(true);
        $user->setVerifiedAt(new \DateTime());
        $customer->setUser($user);
        $this->entityManager()->persist($customer);
        $this->entityManager()->flush();

        return $customer;
    }

    /** A placed order of the customer with the given variants (2 units each by default). */
    private function order(CustomerInterface $customer, ProductVariantInterface ...$variants): OrderInterface
    {
        $channel = $this->channel();
        /** @var FactoryInterface<OrderInterface> $orderFactory */
        $orderFactory = $this->service('sylius.factory.order');
        $order = $orderFactory->createNew();
        $order->setChannel($channel);
        $order->setLocaleCode($this->localeCode());
        $order->setCurrencyCode((string) $channel->getBaseCurrency()?->getCode());
        $order->setCustomerWithAuthorization($customer);
        $order->setTokenValue(bin2hex(random_bytes(10)));

        /** @var FactoryInterface<OrderItemInterface> $itemFactory */
        $itemFactory = $this->service('sylius.factory.order_item');
        /** @var OrderItemQuantityModifierInterface $quantityModifier */
        $quantityModifier = $this->service('sylius.modifier.order_item_quantity');
        /** @var OrderModifierInterface $orderModifier */
        $orderModifier = $this->service('sylius.modifier.order');
        foreach ([] === $variants ? [$this->variant()] : $variants as $variant) {
            $item = $itemFactory->createNew();
            $item->setVariant($variant);
            $quantityModifier->modify($item, 2);
            $orderModifier->addToOrder($order, $item);
        }

        $order->setShippingAddress($this->address());
        $order->setBillingAddress($this->address());
        $this->entityManager()->persist($order);

        $this->shippingMethod();
        $this->apply($order, OrderCheckoutTransitions::TRANSITION_ADDRESS);
        $this->apply($order, OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING);
        $payment = $order->getLastPayment();
        self::assertNotNull($payment);
        $payment->setMethod($this->paymentMethod());
        $this->apply($order, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);
        $this->apply($order, OrderCheckoutTransitions::TRANSITION_COMPLETE);
        $this->entityManager()->flush();

        return $order;
    }

    /** An enabled, untracked variant of a new product sold in the channel. */
    private function variant(int $price = 2500): ProductVariantInterface
    {
        $channel = $this->channel();
        /** @var ProductFactoryInterface<ProductInterface> $productFactory */
        $productFactory = $this->service('sylius.factory.product');
        /** @var FactoryInterface<ChannelPricingInterface> $pricingFactory */
        $pricingFactory = $this->service('sylius.factory.channel_pricing');
        $code = 'mcp_product_' . bin2hex(random_bytes(4));

        $product = $productFactory->createWithVariant();
        $product->setCode($code);
        $product->setCurrentLocale($this->localeCode());
        $product->setFallbackLocale($this->localeCode());
        $product->setName('Test product ' . $code);
        $product->setSlug($code);
        $product->setEnabled(true);
        $product->addChannel($channel);

        $variant = $product->getVariants()->first();
        self::assertInstanceOf(ProductVariantInterface::class, $variant);
        $variant->setCode($code . '_variant');
        $variant->setCurrentLocale($this->localeCode());
        $variant->setFallbackLocale($this->localeCode());
        $variant->setName('Variant ' . $code);
        $variant->setEnabled(true);
        $variant->setTracked(false);
        $variant->setShippingRequired(true);
        $pricing = $pricingFactory->createNew();
        $pricing->setChannelCode($channel->getCode());
        $pricing->setPrice($price);
        $pricing->setOriginalPrice($price);
        $variant->addChannelPricing($pricing);

        $this->entityManager()->persist($product);
        $this->entityManager()->flush();

        return $variant;
    }

    private function paymentMethod(): PaymentMethodInterface
    {
        if (null !== $this->paymentMethod) {
            return $this->paymentMethod;
        }
        /** @var PaymentMethodFactoryInterface<PaymentMethodInterface> $factory */
        $factory = $this->service('sylius.factory.payment_method');
        $method = $factory->createWithGateway('offline');
        $code = 'mcp_pay_' . bin2hex(random_bytes(4));
        $method->setCode($code);
        $method->getGatewayConfig()?->setGatewayName($code);
        $method->setCurrentLocale($this->localeCode());
        $method->setFallbackLocale($this->localeCode());
        $method->setName('Bank transfer ' . $code);
        $method->addChannel($this->channel());
        $method->setEnabled(true);
        $method->setPosition(-1);
        $this->entityManager()->persist($method);
        $this->entityManager()->flush();

        return $this->paymentMethod = $method;
    }

    private function address(): AddressInterface
    {
        /** @var FactoryInterface<AddressInterface> $factory */
        $factory = $this->service('sylius.factory.address');
        $address = $factory->createNew();
        $address->setFirstName('Ada');
        $address->setLastName('Buyer');
        $address->setStreet('1 Main Street');
        $address->setCity('Springfield');
        $address->setPostcode('12345');
        $address->setCountryCode('US');
        $address->setCompany('Acme Industries');

        return $address;
    }

    /** A flat-rate shipping method of the channel shipping to the US (zone created by the test). */
    private function shippingMethod(): ShippingMethodInterface
    {
        if (null !== $this->shippingMethod) {
            return $this->shippingMethod;
        }

        $channel = $this->channel();
        $country = $this->entityManager()->getRepository(CountryInterface::class)->findOneBy(['code' => 'US']);
        if (!$country instanceof CountryInterface) {
            /** @var FactoryInterface<CountryInterface> $countryFactory */
            $countryFactory = $this->service('sylius.factory.country');
            $country = $countryFactory->createNew();
            $country->setCode('US');
            $this->entityManager()->persist($country);
        }
        $country->setEnabled(true);

        /** @var FactoryInterface<ZoneInterface> $zoneFactory */
        $zoneFactory = $this->service('sylius.factory.zone');
        /** @var FactoryInterface<ZoneMemberInterface> $memberFactory */
        $memberFactory = $this->service('sylius.factory.zone_member');
        $zone = $zoneFactory->createNew();
        $zone->setCode('MCP_' . strtoupper(bin2hex(random_bytes(4))));
        $zone->setName((string) $zone->getCode());
        $zone->setType(ZoneInterface::TYPE_COUNTRY);
        $member = $memberFactory->createNew();
        $member->setCode('US');
        $zone->addMember($member);
        $this->entityManager()->persist($zone);

        /** @var FactoryInterface<ShippingMethodInterface> $factory */
        $factory = $this->service('sylius.factory.shipping_method');
        $method = $factory->createNew();
        $code = 'mcp_ship_' . bin2hex(random_bytes(4));
        $method->setCode($code);
        $method->setCurrentLocale($this->localeCode());
        $method->setFallbackLocale($this->localeCode());
        $method->setName('Test ' . $code);
        $method->setZone($zone);
        $method->setCalculator('flat_rate');
        $method->setConfiguration([(string) $channel->getCode() => ['amount' => 500]]);
        $method->addChannel($channel);
        $method->setEnabled(true);
        $method->setPosition(-1);
        $this->entityManager()->persist($method);
        $this->entityManager()->flush();

        return $this->shippingMethod = $method;
    }

    private function apply(OrderInterface $order, string $transition): void
    {
        /** @var StateMachineInterface $stateMachine */
        $stateMachine = $this->service('sylius_abstraction.state_machine');
        self::assertTrue($stateMachine->can($order, OrderCheckoutTransitions::GRAPH, $transition), sprintf('Checkout transition "%s" from "%s"', $transition, (string) $order->getCheckoutState()));
        $stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, $transition);
    }

    /** The cart of the customer as the shop sees it after login. */
    private function cartOf(CustomerInterface $customer): ?OrderInterface
    {
        $this->entityManager()->clear();
        /** @var \Sylius\Component\Core\Repository\OrderRepositoryInterface<OrderInterface> $repository */
        $repository = $this->service('sylius.repository.order');
        $customer = $this->entityManager()->find(CustomerInterface::class, $customer->getId());
        self::assertInstanceOf(CustomerInterface::class, $customer);

        return $repository->findLatestNotEmptyCartByChannelAndCustomer($this->channel(), $customer);
    }

    private function loginShop(CustomerInterface $customer): void
    {
        $user = $customer->getUser();
        self::assertInstanceOf(ShopUserInterface::class, $user);
        $this->entityManager()->flush();
        $this->client->loginUser($user, 'shop');
    }

    private function service(string $id): object
    {
        // The test container throws for unknown services.
        return self::getContainer()->get($id);
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    private function connection(): Connection
    {
        return $this->entityManager()->getConnection();
    }
}
