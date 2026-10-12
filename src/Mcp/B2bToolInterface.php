<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Mcp;

use Mcp\Schema\Tool;

/**
 * A B2B tool registered on the official MCP server. The service id is the class name and the
 * MCP SDK calls __invoke() with the arguments named after the input schema properties.
 */
interface B2bToolInterface
{
    public static function getDefinition(): Tool;
}
