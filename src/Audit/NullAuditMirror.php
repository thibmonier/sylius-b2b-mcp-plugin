<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Audit;

use Tmonier\SyliusB2bMcpPlugin\Entity\ConnectionInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\ToolCallInterface;

final class NullAuditMirror implements AuditMirrorInterface
{
    public function toolCalled(ToolCallInterface $call): void
    {
    }

    public function connectionApproved(ConnectionInterface $connection, array $previousScopes): void
    {
    }

    public function connectionRevoked(ConnectionInterface $connection): void
    {
    }
}
