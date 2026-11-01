<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Mcp;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\Mcp\ToolRefusal;

final class ToolRefusalTest extends TestCase
{
    public function testIsRuntimeException(): void
    {
        $refusal = new ToolRefusal('Not allowed');

        self::assertInstanceOf(\RuntimeException::class, $refusal);
        self::assertSame('Not allowed', $refusal->getMessage());
    }
}
