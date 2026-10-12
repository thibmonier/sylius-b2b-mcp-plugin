<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Mcp;

use Mcp\Capability\Discovery\DiscoveryState;
use Mcp\Capability\Registry\PromptReference;
use Mcp\Capability\Registry\ResourceReference;
use Mcp\Capability\Registry\ResourceTemplateReference;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Page;
use Mcp\Schema\Prompt;
use Mcp\Schema\Resource;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;

/**
 * Decorates the MCP registry to leave out the tools of sylius/mcp-server-plugin listed in
 * "hidden_official_tools" (by default its guest cart and checkout tools: the B2B tools hand the
 * checkout over to the shop instead).
 */
final class HiddenToolsRegistry implements RegistryInterface
{
    /** @param list<string> $hiddenTools */
    public function __construct(
        private readonly RegistryInterface $registry,
        private readonly array $hiddenTools,
    ) {
    }

    public function registerTool(Tool $tool, callable|array|string $handler, bool $isManual = false): void
    {
        if (\in_array($tool->name, $this->hiddenTools, true)) {
            return;
        }

        $this->registry->registerTool($tool, $handler, $isManual);
    }

    public function registerResource(Resource $resource, callable|array|string $handler, bool $isManual = false): void
    {
        $this->registry->registerResource($resource, $handler, $isManual);
    }

    public function registerResourceTemplate(ResourceTemplate $template, callable|array|string $handler, array $completionProviders = [], bool $isManual = false): void
    {
        $this->registry->registerResourceTemplate($template, $handler, $completionProviders, $isManual);
    }

    public function registerPrompt(Prompt $prompt, callable|array|string $handler, array $completionProviders = [], bool $isManual = false): void
    {
        $this->registry->registerPrompt($prompt, $handler, $completionProviders, $isManual);
    }

    public function clear(): void
    {
        $this->registry->clear();
    }

    public function getDiscoveryState(): DiscoveryState
    {
        return $this->registry->getDiscoveryState();
    }

    public function setDiscoveryState(DiscoveryState $state): void
    {
        $this->registry->setDiscoveryState($state);
    }

    public function hasTools(): bool
    {
        return $this->registry->hasTools();
    }

    public function getTools(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->registry->getTools($limit, $cursor);
    }

    public function getTool(string $name): ToolReference
    {
        return $this->registry->getTool($name);
    }

    public function hasResources(): bool
    {
        return $this->registry->hasResources();
    }

    public function getResources(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->registry->getResources($limit, $cursor);
    }

    public function getResource(string $uri, bool $includeTemplates = true): ResourceReference|ResourceTemplateReference
    {
        return $this->registry->getResource($uri, $includeTemplates);
    }

    public function hasResourceTemplates(): bool
    {
        return $this->registry->hasResourceTemplates();
    }

    public function getResourceTemplates(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->registry->getResourceTemplates($limit, $cursor);
    }

    public function getResourceTemplate(string $uriTemplate): ResourceTemplateReference
    {
        return $this->registry->getResourceTemplate($uriTemplate);
    }

    public function hasPrompts(): bool
    {
        return $this->registry->hasPrompts();
    }

    public function getPrompts(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->registry->getPrompts($limit, $cursor);
    }

    public function getPrompt(string $name): PromptReference
    {
        return $this->registry->getPrompt($name);
    }
}
