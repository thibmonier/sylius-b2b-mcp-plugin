<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Mcp;

/**
 * A request the tool declines (not found, not allowed, invalid input): the message is returned to
 * the assistant as a tool error and recorded as "refused" in the audit log.
 */
final class ToolRefusal extends \RuntimeException
{
}
