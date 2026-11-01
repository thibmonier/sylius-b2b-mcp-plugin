<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Audit;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\Audit\AuditLogMirror;

final class AuditLogMirrorConstantsTest extends TestCase
{
    public function testConstants(): void
    {
        self::assertSame('mcp_tool_call', AuditLogMirror::RESOURCE_TOOL_CALL);
        self::assertSame('mcp_connection', AuditLogMirror::RESOURCE_CONNECTION);
    }
}
