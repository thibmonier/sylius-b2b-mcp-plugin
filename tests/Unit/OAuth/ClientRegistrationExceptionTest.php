<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\OAuth;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\OAuth\ClientRegistrationException;

final class ClientRegistrationExceptionTest extends TestCase
{
    public function testIsInvalidArgumentException(): void
    {
        $e = new ClientRegistrationException('invalid_redirect_uri', 'Bad redirect');

        self::assertInstanceOf(\InvalidArgumentException::class, $e);
    }

    public function testErrorAndMessage(): void
    {
        $e = new ClientRegistrationException('invalid_client_metadata', 'Missing field');

        self::assertSame('invalid_client_metadata', $e->error);
        self::assertSame('Missing field', $e->getMessage());
    }
}
