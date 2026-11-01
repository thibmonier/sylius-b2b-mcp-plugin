<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Mcp;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\Audit\ToolCallLogger;
use Tmonier\SyliusB2bMcpPlugin\Idempotency\IdempotencyStore;
use Tmonier\SyliusB2bMcpPlugin\Mcp\ToolRunner;
use Tmonier\SyliusB2bMcpPlugin\Security\CurrentConnection;

final class ToolRunnerTest extends TestCase
{
    public function testConstructorDependencies(): void
    {
        $ref = new \ReflectionClass(ToolRunner::class);
        $constructor = $ref->getConstructor();

        self::assertNotNull($constructor);
        $params = $constructor->getParameters();
        self::assertCount(3, $params);

        $type0 = $params[0]->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $type0);
        self::assertSame(CurrentConnection::class, $type0->getName());

        $type1 = $params[1]->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $type1);
        self::assertSame(IdempotencyStore::class, $type1->getName());

        $type2 = $params[2]->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $type2);
        self::assertSame(ToolCallLogger::class, $type2->getName());
    }

    public function testIsFinalReadonly(): void
    {
        $ref = new \ReflectionClass(ToolRunner::class);

        self::assertTrue($ref->isFinal());
        self::assertTrue($ref->isReadOnly());
    }

    public function testRunMethodSignature(): void
    {
        $ref = new \ReflectionClass(ToolRunner::class);
        $run = $ref->getMethod('run');

        self::assertTrue($run->isPublic());
        $params = $run->getParameters();
        self::assertSame('tool', $params[0]->getName());
        self::assertSame('scope', $params[1]->getName());
        self::assertSame('arguments', $params[2]->getName());
        self::assertSame('handler', $params[3]->getName());
        self::assertSame('idempotencyKey', $params[4]->getName());
        self::assertTrue($params[4]->isOptional());
        self::assertNull($params[4]->getDefaultValue());
    }
}
