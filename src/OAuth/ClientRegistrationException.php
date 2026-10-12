<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth;

/** RFC 7591 error ("invalid_redirect_uri" or "invalid_client_metadata"). */
final class ClientRegistrationException extends \InvalidArgumentException
{
    public function __construct(public readonly string $error, string $description)
    {
        parent::__construct($description);
    }
}
