<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Controller\OAuth;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tmonier\SyliusB2bMcpPlugin\OAuth\ClientRegistrar;
use Tmonier\SyliusB2bMcpPlugin\OAuth\McpEndpoint;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Scopes;

/** RFC 9728 protected resource metadata and RFC 8414 authorization server metadata. */
final class MetadataController
{
    public function __construct(
        private readonly McpEndpoint $endpoint,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly bool $dynamicClientRegistration,
    ) {
    }

    public function protectedResource(Request $request, string $resourcePath = ''): JsonResponse
    {
        if ('' !== $resourcePath && '/' . ltrim($resourcePath, '/') !== $this->endpoint->getPath()) {
            throw new NotFoundHttpException();
        }

        return $this->json([
            'resource' => $this->endpoint->getResourceUrl($request),
            'authorization_servers' => [$this->endpoint->getIssuer($request)],
            'scopes_supported' => Scopes::ALL,
            'bearer_methods_supported' => ['header'],
        ]);
    }

    public function authorizationServer(Request $request): JsonResponse
    {
        $url = fn (string $route): string => $this->urlGenerator->generate($route, [], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->json(array_filter([
            'issuer' => $this->endpoint->getIssuer($request),
            'authorization_endpoint' => $url('tmonier_b2b_mcp_oauth_authorize'),
            'token_endpoint' => $url('tmonier_b2b_mcp_oauth_token'),
            'registration_endpoint' => $this->dynamicClientRegistration ? $url('tmonier_b2b_mcp_oauth_register') : null,
            'revocation_endpoint' => $url('tmonier_b2b_mcp_oauth_revoke'),
            'scopes_supported' => Scopes::ALL,
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ClientRegistrar::AUTH_METHODS,
            'revocation_endpoint_auth_methods_supported' => ClientRegistrar::AUTH_METHODS,
            'code_challenge_methods_supported' => ['S256'],
        ], static fn (mixed $value): bool => null !== $value));
    }

    /** @param array<string, mixed> $data */
    private function json(array $data): JsonResponse
    {
        $response = new JsonResponse($data);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
