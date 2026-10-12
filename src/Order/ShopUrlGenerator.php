<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Order;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tmonier\SyliusB2bMcpPlugin\Security\AuthenticatedConnection;

/** Absolute links to the shop pages (the customer signs in there with the same account). */
final class ShopUrlGenerator
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    /** @param array<string, string|int> $parameters */
    public function generate(AuthenticatedConnection $connection, string $route, array $parameters = [], ?string $localeCode = null): string
    {
        $parameters['_locale'] = $localeCode ?? $connection->channel->getDefaultLocale()?->getCode() ?? 'en_US';

        return $this->urlGenerator->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
