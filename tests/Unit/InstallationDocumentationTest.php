<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The Flex recipe of sylius/mcp-server-plugin already imports the routes of the MCP server: importing them a second time
 * makes the router fail ("Do not add the mcp loader twice"). The READMEs ask for that import only in a project without Flex.
 */
final class InstallationDocumentationTest extends TestCase
{
    private const MCP_SERVER_ROUTES = '@SyliusMcpServerPlugin/config/routes.yaml';

    private const PLUGIN_ROUTES = '@TmonierSyliusB2bMcpPlugin/config/routes.yaml';

    /** @return iterable<string, array{string}> */
    public static function readmes(): iterable
    {
        yield 'README.md' => ['README.md'];
        yield 'README.fr.md' => ['README.fr.md'];
    }

    #[DataProvider('readmes')]
    public function testTheRoutesOfThePluginAreImportedWithoutTheOnesOfTheMcpServer(string $readme): void
    {
        $blocks = self::yamlBlocksImporting(self::PLUGIN_ROUTES, $this->read($readme));

        self::assertCount(1, $blocks);
        self::assertStringNotContainsString(self::MCP_SERVER_ROUTES, $blocks[0]['yaml']);
    }

    #[DataProvider('readmes')]
    public function testTheRoutesOfTheMcpServerAreImportedOnceAndOnlyWithoutFlex(string $readme): void
    {
        $blocks = self::yamlBlocksImporting(self::MCP_SERVER_ROUTES, $this->read($readme));

        self::assertCount(1, $blocks);
        self::assertStringContainsString('Flex', $blocks[0]['introduction']);
    }

    public function testADuplicatedImportIsDetected(): void
    {
        $duplicated = <<<'MD'
            3. Import the routes:

                ```yaml
                sylius_mcp_server:
                    resource: "@SyliusMcpServerPlugin/config/routes.yaml"

                tmonier_sylius_b2b_mcp:
                    resource: "@TmonierSyliusB2bMcpPlugin/config/routes.yaml"
                ```
            MD;

        $blocks = self::yamlBlocksImporting(self::PLUGIN_ROUTES, $duplicated);

        self::assertStringContainsString(self::MCP_SERVER_ROUTES, $blocks[0]['yaml']);
    }

    /** @return list<array{introduction: string, yaml: string}> */
    private static function yamlBlocksImporting(string $resource, string $markdown): array
    {
        preg_match_all('/```yaml\n(.*?)```/s', $markdown, $matches, \PREG_OFFSET_CAPTURE);
        $blocks = [];
        $previousEnd = 0;
        foreach ($matches[1] as [$yaml, $offset]) {
            $introduction = substr($markdown, $previousEnd, $offset - $previousEnd);
            $previousEnd = $offset + strlen($yaml);
            if (str_contains($yaml, $resource)) {
                $blocks[] = ['introduction' => $introduction, 'yaml' => $yaml];
            }
        }

        return $blocks;
    }

    private function read(string $readme): string
    {
        return (string) file_get_contents(__DIR__ . '/../../' . $readme);
    }
}
