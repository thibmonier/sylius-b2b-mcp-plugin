<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Controller\OAuth;

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tmonier\SyliusB2bMcpPlugin\Entity\OAuthToken;
use Tmonier\SyliusB2bMcpPlugin\OAuth\RefreshTokenDecoder;
use Tmonier\SyliusB2bMcpPlugin\OAuth\TokenStore;
use Tmonier\SyliusB2bMcpPlugin\RateLimit\RateLimiter;

/** RFC 7009 token revocation (access or refresh token of the authenticated client). */
final readonly class RevocationController
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private TokenStore $tokenStore,
        private RateLimiter $rateLimiter,
        private RefreshTokenDecoder $refreshTokenDecoder,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $limited = $this->rateLimiter->consumeOAuthRequest($request->getClientIp(), $request->getUser() ?? $request->request->get('client_id'));
        if ($limited instanceof \Symfony\Component\HttpFoundation\Response) {
            return $limited;
        }

        $clientId = $request->getUser() ?? $request->request->get('client_id');
        $clientSecret = $request->getPassword() ?? $request->request->get('client_secret');
        if (!\is_string($clientId) || !$this->clientRepository->validateClient($clientId, \is_string($clientSecret) ? $clientSecret : null, null)) {
            return new JsonResponse(['error' => 'invalid_client'], Response::HTTP_UNAUTHORIZED, ['Cache-Control' => 'no-store']);
        }

        $value = $request->request->get('token');
        if (!\is_string($value) || '' === $value) {
            return new JsonResponse(['error' => 'invalid_request'], Response::HTTP_BAD_REQUEST, ['Cache-Control' => 'no-store']);
        }

        // Access tokens are opaque identifiers; refresh tokens carry an encrypted payload.
        $candidates = [OAuthToken::TYPE_ACCESS => $value, OAuthToken::TYPE_REFRESH => $this->refreshTokenDecoder->getIdentifier($value)];
        foreach ($candidates as $type => $identifier) {
            $token = null === $identifier ? null : $this->tokenStore->find($type, $identifier);
            if ($token instanceof \Tmonier\SyliusB2bMcpPlugin\Entity\OAuthToken && $token->getConnection()->getClient()?->getIdentifier() === $clientId) {
                $this->tokenStore->revoke($type, $identifier);
            }
        }

        // RFC 7009 2.2: the same answer whether the token existed or not.
        return new Response('', Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }
}
