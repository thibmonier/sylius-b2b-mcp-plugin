<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Controller\OAuth;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tmonier\SyliusB2bMcpPlugin\OAuth\PsrBridge;
use Tmonier\SyliusB2bMcpPlugin\RateLimit\RateLimiter;

/** Token endpoint: authorization code (with PKCE verifier) and refresh token grants. */
final readonly class TokenController
{
    public function __construct(
        private AuthorizationServer $authorizationServer,
        private PsrBridge $psrBridge,
        private RateLimiter $rateLimiter,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $limited = $this->rateLimiter->consumeOAuthRequest($request->getClientIp(), $request->getUser() ?? $request->request->get('client_id'));
        if ($limited instanceof \Symfony\Component\HttpFoundation\Response) {
            return $limited;
        }

        try {
            $response = $this->authorizationServer->respondToAccessTokenRequest($this->psrBridge->toPsr($request), $this->psrBridge->createPsrResponse());
        } catch (OAuthServerException $exception) {
            $response = $exception->generateHttpResponse($this->psrBridge->createPsrResponse());
        }

        return $this->psrBridge->toSymfony($response);
    }
}
