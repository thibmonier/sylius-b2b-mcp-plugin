<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Order;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\ConnectionInterface;
use Tmonier\SyliusB2bMcpPlugin\Order\ShopUrlGenerator;
use Tmonier\SyliusB2bMcpPlugin\Security\AuthenticatedConnection;

final class ShopUrlGeneratorTest extends TestCase
{
    public function testGenerateUsesChannelDefaultLocale(): void
    {
        $locale = $this->createMock(LocaleInterface::class);
        $locale->method('getCode')->willReturn('fr_FR');

        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getDefaultLocale')->willReturn($locale);

        $connection = new AuthenticatedConnection(
            $this->createMock(ConnectionInterface::class),
            $this->createMock(ShopUserInterface::class),
            $this->createMock(CustomerInterface::class),
            $channel,
            ['b2b:read'],
        );

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->expects(self::once())
            ->method('generate')
            ->with('sylius_shop_order_show', ['_locale' => 'fr_FR', 'number' => '000001'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://shop.test/fr_FR/orders/000001');

        $generator = new ShopUrlGenerator($router);
        $url = $generator->generate($connection, 'sylius_shop_order_show', ['number' => '000001']);

        self::assertSame('https://shop.test/fr_FR/orders/000001', $url);
    }

    public function testGenerateWithExplicitLocale(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getDefaultLocale')->willReturn(null);

        $connection = new AuthenticatedConnection(
            $this->createMock(ConnectionInterface::class),
            $this->createMock(ShopUserInterface::class),
            $this->createMock(CustomerInterface::class),
            $channel,
            [],
        );

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->expects(self::once())
            ->method('generate')
            ->with('route', ['_locale' => 'de_DE'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://shop.test/de_DE');

        $generator = new ShopUrlGenerator($router);
        $url = $generator->generate($connection, 'route', [], 'de_DE');

        self::assertSame('https://shop.test/de_DE', $url);
    }
}
