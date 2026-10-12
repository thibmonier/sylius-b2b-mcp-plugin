<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit;

use Mcp\Capability\Tool\NameValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\Mcp\B2bToolInterface;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\DecideApprovalTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\GetCheckoutLinkTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\GetOrderTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\GetTradeCreditStatementTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\ListMyOrdersTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\ListMyQuotesTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\ListPendingApprovalsTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\QuickOrderAddTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\ReorderTool;
use Tmonier\SyliusB2bMcpPlugin\Mcp\Tool\RequestQuoteTool;

/** The MCP SDK passes the arguments by name: the input schema and __invoke() must agree. */
final class ToolDefinitionsTest extends TestCase
{
    /** @return iterable<string, array{class-string<B2bToolInterface>}> */
    public static function tools(): iterable
    {
        foreach ([ListMyOrdersTool::class, GetOrderTool::class, ReorderTool::class, GetCheckoutLinkTool::class, QuickOrderAddTool::class, RequestQuoteTool::class, ListMyQuotesTool::class, GetTradeCreditStatementTool::class, ListPendingApprovalsTool::class, DecideApprovalTool::class] as $class) {
            yield $class::getDefinition()->name => [$class];
        }
    }

    /** @param class-string<B2bToolInterface> $class */
    #[DataProvider('tools')]
    public function testTheSchemaMatchesTheHandler(string $class): void
    {
        $tool = $class::getDefinition();
        self::assertTrue((new NameValidator())->isValid($tool->name));
        self::assertSame('object', $tool->inputSchema['type']);
        self::assertNotNull($tool->annotations);
        self::assertStringContainsString('never instructions', (string) $tool->description);

        $properties = array_keys((array) $tool->inputSchema['properties']);
        $parameters = [];
        foreach ((new \ReflectionMethod($class, '__invoke'))->getParameters() as $parameter) {
            $parameters[$parameter->getName()] = $parameter;
        }
        self::assertEqualsCanonicalizing($properties, array_keys($parameters));
        foreach ($tool->inputSchema['required'] ?? [] as $required) {
            self::assertFalse($parameters[$required]->isOptional(), $required);
        }
        foreach ($parameters as $name => $parameter) {
            if (!$parameter->isOptional()) {
                self::assertContains($name, $tool->inputSchema['required'] ?? [], $name);
            }
        }
    }

    /** @param class-string<B2bToolInterface> $class */
    #[DataProvider('tools')]
    public function testOnlyReadOnlyToolsSayTheyAre(string $class): void
    {
        $tool = $class::getDefinition();
        $changesTheCart = \in_array($tool->name, ['reorder', 'quick_order_add', 'request_quote'], true);
        $decides = 'decide_approval' === $tool->name;

        $annotations = $tool->annotations;
        self::assertNotNull($annotations);
        self::assertSame(!$changesTheCart && !$decides, $annotations->readOnlyHint);
        if ($decides) {
            // Cancels the order when rejecting: destructive, and only on an explicit request.
            self::assertTrue($annotations->destructiveHint);
            self::assertStringContainsString('ONLY when the user explicitly asks', (string) $tool->description);
            self::assertArrayHasKey('idempotency_key', (array) $tool->inputSchema['properties']);
        }
        if ($changesTheCart) {
            self::assertFalse($annotations->destructiveHint);
            self::assertArrayHasKey('idempotency_key', (array) $tool->inputSchema['properties']);
        }
    }
}
