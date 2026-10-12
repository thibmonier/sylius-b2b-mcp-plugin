<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Scopes;

final class ScopesTest extends TestCase
{
    public function testUnknownScopesAreDroppedAndTheOrderIsStable(): void
    {
        self::assertSame(['b2b:read', 'b2b:cart'], Scopes::normalize(['b2b:cart', 'admin', 'b2b:read', 'b2b:read']));
        self::assertSame([], Scopes::normalize(['openid']));
    }
}
