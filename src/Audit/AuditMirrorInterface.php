<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Audit;

use Tmonier\SyliusB2bMcpPlugin\Entity\ConnectionInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\ToolCallInterface;

/**
 * Copies the security events of the plugin into another audit log (tmonier/sylius-audit-log-plugin).
 * The own audit table stays the reference; a failing mirror never breaks the call.
 */
interface AuditMirrorInterface
{
    public function toolCalled(ToolCallInterface $call): void;

    /** @param list<string> $previousScopes empty for a new connection */
    public function connectionApproved(ConnectionInterface $connection, array $previousScopes): void;

    public function connectionRevoked(ConnectionInterface $connection): void;
}
