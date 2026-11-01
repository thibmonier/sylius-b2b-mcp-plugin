<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\OAuth;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Scopes;

final class ScopesTest extends TestCase
{
    public function testNormalizeKeepsKnownScopes(): void
    {
        self::assertSame(['b2b:read', 'b2b:cart', 'b2b:approve'], Scopes::normalize(['b2b:read', 'b2b:cart', 'b2b:approve']));
    }

    public function testNormalizeFiltersUnknown(): void
    {
        self::assertSame(['b2b:read'], Scopes::normalize(['b2b:read', 'unknown:scope']));
    }

    public function testNormalizePreservesOrder(): void
    {
        self::assertSame(['b2b:read', 'b2b:cart'], Scopes::normalize(['b2b:cart', 'b2b:read']));
    }

    public function testNormalizeEmpty(): void
    {
        self::assertSame([], Scopes::normalize([]));
    }

    public function testConstants(): void
    {
        self::assertSame('b2b:read', Scopes::READ);
        self::assertSame('b2b:cart', Scopes::CART);
        self::assertSame('b2b:approve', Scopes::APPROVE);
        self::assertSame([Scopes::READ, Scopes::CART], Scopes::DEFAULT);
        self::assertSame([Scopes::READ, Scopes::CART, Scopes::APPROVE], Scopes::ALL);
    }
}
