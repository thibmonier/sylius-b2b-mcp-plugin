<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth;

use Symfony\Component\HttpFoundation\Request;

/** URLs of the protected MCP endpoint and of its metadata, for the host of the request (one issuer per shop host). */
final class McpEndpoint
{
    public const PROTECTED_RESOURCE_METADATA = '/.well-known/oauth-protected-resource';

    public const AUTHORIZATION_SERVER_METADATA = '/.well-known/oauth-authorization-server';

    public function __construct(private readonly string $path)
    {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getIssuer(Request $request): string
    {
        return $request->getSchemeAndHttpHost();
    }

    public function getResourceUrl(Request $request): string
    {
        return $this->getIssuer($request) . $this->path;
    }

    /** RFC 9728: the resource path is appended to the well-known path. */
    public function getResourceMetadataUrl(Request $request): string
    {
        return $this->getIssuer($request) . self::PROTECTED_RESOURCE_METADATA . $this->path;
    }

    /**
     * Whether the request reaches the MCP controller of symfony/mcp-bundle. Decided on the route
     * matched by the router (which decodes the path: /%5Fmcp is /_mcp), with the decoded path as
     * a fallback when routing did not run yet.
     */
    public function isEndpoint(Request $request): bool
    {
        $controller = $request->attributes->get('_controller');
        if (\is_string($controller) && str_starts_with($controller, 'mcp.server.')) {
            return true;
        }

        return rtrim(rawurldecode($request->getPathInfo()), '/') === rtrim($this->path, '/');
    }
}
