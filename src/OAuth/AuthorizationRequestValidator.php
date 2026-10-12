<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Symfony\Component\HttpFoundation\Request;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Entity\ClientEntity;

/**
 * Validates an authorization request with league/oauth2-server, plus the rules of the MCP
 * authorization spec that the library leaves optional: PKCE with S256 for every client, redirect
 * URI still allowed by the shop, and the resource indicator (RFC 8707) naming this MCP endpoint.
 */
final class AuthorizationRequestValidator
{
    public function __construct(
        private readonly AuthorizationServer $authorizationServer,
        private readonly PsrBridge $psrBridge,
        private readonly RedirectUriPolicy $redirectUriPolicy,
        private readonly McpEndpoint $endpoint,
    ) {
    }

    /** @throws OAuthServerException */
    public function validate(Request $request): AuthorizationRequestInterface
    {
        // Only the query string carries an authorization request.
        $query = Request::create($request->getUri(), 'GET', [], [], [], $request->server->all());
        $authorizationRequest = $this->authorizationServer->validateAuthorizationRequest($this->psrBridge->toPsr($query));

        if ('S256' !== $authorizationRequest->getCodeChallengeMethod() || '' === (string) $authorizationRequest->getCodeChallenge()) {
            throw OAuthServerException::invalidRequest('code_challenge', 'PKCE with the S256 method is required.');
        }

        $client = $authorizationRequest->getClient();
        $redirectUri = $authorizationRequest->getRedirectUri() ?? ($client->getRedirectUri()[0] ?? '');
        if (!$this->redirectUriPolicy->isAllowed($redirectUri)) {
            throw OAuthServerException::invalidRequest('redirect_uri', 'This redirect URI is no longer allowed by the shop.');
        }

        $resource = $request->query->get('resource');
        if (null !== $resource && rtrim((string) $resource, '/') !== rtrim($this->endpoint->getResourceUrl($request), '/')) {
            throw new OAuthServerException('The requested resource is not served by this authorization server.', 3, 'invalid_target', 400);
        }

        if (!$client instanceof ClientEntity) {
            throw OAuthServerException::invalidClient($this->psrBridge->toPsr($query));
        }

        return $authorizationRequest;
    }

    public function getRedirectHost(AuthorizationRequestInterface $authorizationRequest): string
    {
        $redirectUri = $authorizationRequest->getRedirectUri() ?? ($authorizationRequest->getClient()->getRedirectUri()[0] ?? '');

        return (string) parse_url((string) $redirectUri, \PHP_URL_HOST);
    }
}
