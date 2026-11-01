<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\OAuth;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Tmonier\SyliusB2bMcpPlugin\OAuth\McpEndpoint;

final class McpEndpointTest extends TestCase
{
    private McpEndpoint $endpoint;

    protected function setUp(): void
    {
        $this->endpoint = new McpEndpoint('/_mcp');
    }

    public function testGetPath(): void
    {
        self::assertSame('/_mcp', $this->endpoint->getPath());
    }

    public function testGetIssuer(): void
    {
        $request = Request::create('https://shop.example.com/_mcp');

        self::assertSame('https://shop.example.com', $this->endpoint->getIssuer($request));
    }

    public function testGetResourceUrl(): void
    {
        $request = Request::create('https://shop.example.com/_mcp');

        self::assertSame('https://shop.example.com/_mcp', $this->endpoint->getResourceUrl($request));
    }

    public function testGetResourceMetadataUrl(): void
    {
        $request = Request::create('https://shop.example.com/_mcp');

        self::assertSame(
            'https://shop.example.com/.well-known/oauth-protected-resource/_mcp',
            $this->endpoint->getResourceMetadataUrl($request),
        );
    }

    public function testIsEndpointByPath(): void
    {
        $request = Request::create('https://shop.example.com/_mcp');

        self::assertTrue($this->endpoint->isEndpoint($request));
    }

    public function testIsEndpointByController(): void
    {
        $request = Request::create('https://shop.example.com/something');
        $request->attributes->set('_controller', 'mcp.server.default');

        self::assertTrue($this->endpoint->isEndpoint($request));
    }

    public function testIsNotEndpoint(): void
    {
        $request = Request::create('https://shop.example.com/other');

        self::assertFalse($this->endpoint->isEndpoint($request));
    }

    public function testIsEndpointWithEncodedPath(): void
    {
        $request = Request::create('https://shop.example.com/%5Fmcp');

        self::assertTrue($this->endpoint->isEndpoint($request));
    }
}
