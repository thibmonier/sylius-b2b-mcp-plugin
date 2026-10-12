<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tmonier\SyliusB2bMcpPlugin\Entity\Connection;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Repository\ScopeRepository;
use Tmonier\SyliusB2bMcpPlugin\OAuth\ScopeCatalog;
use Tmonier\SyliusB2bMcpPlugin\OAuth\TokenStore;

final class ScopeCatalogTest extends TestCase
{
    public function testTheApprovalScopeOnlyExistsWhenDecisionsAreEnabled(): void
    {
        $disabled = new ScopeCatalog(false);
        self::assertSame(['b2b:read', 'b2b:cart'], $disabled->supported());
        self::assertFalse($disabled->isSupported('b2b:approve'));
        self::assertNull($this->repository($disabled)->getScopeEntityByIdentifier('b2b:approve'));

        $enabled = new ScopeCatalog(true);
        self::assertSame(['b2b:read', 'b2b:cart', 'b2b:approve'], $enabled->supported());
        self::assertNotNull($this->repository($enabled)->getScopeEntityByIdentifier('b2b:approve'));
        self::assertNull($this->repository($enabled)->getScopeEntityByIdentifier('admin'));
    }

    private function repository(ScopeCatalog $catalog): ScopeRepository
    {
        return new ScopeRepository(new TokenStore($this->createMock(EntityManagerInterface::class), new MockClock(), Connection::class), $catalog);
    }
}
