<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Security;

use Symfony\Component\HttpFoundation\RequestStack;

/** Reads the connection that the bearer authenticator stored on the current request. */
final class CurrentConnection
{
    public const REQUEST_ATTRIBUTE = '_tmonier_b2b_mcp_connection';

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function get(): ?AuthenticatedConnection
    {
        $connection = $this->requestStack->getMainRequest()?->attributes->get(self::REQUEST_ATTRIBUTE);

        return $connection instanceof AuthenticatedConnection ? $connection : null;
    }

    public function getIpAddress(): ?string
    {
        return $this->requestStack->getMainRequest()?->getClientIp();
    }
}
