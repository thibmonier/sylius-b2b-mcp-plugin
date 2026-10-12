<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Mcp;

use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Capability\RegistryInterface;

/**
 * Registers the enabled B2B tools on the server of symfony/mcp-bundle (the extension point used
 * by sylius/mcp-server-plugin itself). Tools whose plugin is not installed are not defined in the
 * container, hence never listed.
 */
final class B2bToolLoader implements LoaderInterface
{
    /** @param iterable<B2bToolInterface> $tools */
    public function __construct(private readonly iterable $tools)
    {
    }

    public function load(RegistryInterface $registry): void
    {
        foreach ($this->tools as $tool) {
            $registry->registerTool($tool::getDefinition(), $tool::class, isManual: true);
        }
    }
}
