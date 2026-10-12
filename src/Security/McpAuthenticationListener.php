<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Security;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Tmonier\SyliusB2bMcpPlugin\OAuth\McpEndpoint;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Scopes;
use Tmonier\SyliusB2bMcpPlugin\OAuth\TokenStore;
use Tmonier\SyliusB2bMcpPlugin\RateLimit\RateLimiter;

/**
 * Bearer token authentication of the MCP endpoint, without any change to security.yaml
 * (both listeners run after the router, priority 32, so the matched route decides):
 *
 * - before the firewall: every request needs a valid access token issued for this channel to an
 *   enabled shop user, otherwise a 401 with the RFC 9728 "resource_metadata" pointer that starts
 *   the OAuth flow in Claude and ChatGPT (a shop session cookie is never enough);
 * - after the firewall (the "shop" firewall matches the path but finds no session): the shop
 *   user becomes the security token of the request, so that the Sylius and plugin voters apply,
 *   and the firewall is told not to store it in a session (stateless).
 */
final class McpAuthenticationListener
{
    public const FIREWALL_NAME = 'tmonier_b2b_mcp';

    private const LAST_USED_RESOLUTION = 'PT5M';

    public function __construct(
        private readonly TokenStore $tokenStore,
        private readonly ChannelContextInterface $channelContext,
        private readonly McpEndpoint $endpoint,
        private readonly EntityManagerInterface $entityManager,
        private readonly RateLimiter $rateLimiter,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function authenticate(RequestEvent $event): void
    {
        $request = $event->getRequest();
        // CORS preflight requests carry no credentials.
        if (!$event->isMainRequest() || !$this->endpoint->isEndpoint($request) || $request->isMethod('OPTIONS')) {
            return;
        }
        $request->attributes->remove(CurrentConnection::REQUEST_ATTRIBUTE);

        $header = (string) $request->headers->get('Authorization', '');
        if (1 !== preg_match('/^Bearer\s+([A-Za-z0-9._~+\/=-]{20,512})$/', $header, $matches)) {
            $event->setResponse($this->unauthorized($request, null));

            return;
        }

        $connection = $this->resolve($matches[1]);
        if (null === $connection) {
            $event->setResponse($this->unauthorized($request, 'invalid_token'));

            return;
        }

        $limited = $this->rateLimiter->consumeMcpRequest('connection-' . $connection->connection->getId());
        if (null !== $limited) {
            $event->setResponse($limited);

            return;
        }

        $request->attributes->set(CurrentConnection::REQUEST_ATTRIBUTE, $connection);
        $this->touch($connection);
    }

    public function setSecurityToken(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $connection = $request->attributes->get(CurrentConnection::REQUEST_ATTRIBUTE);
        if (!$event->isMainRequest() || !$connection instanceof AuthenticatedConnection || !$this->endpoint->isEndpoint($request)) {
            return;
        }

        $user = $connection->shopUser;
        $this->tokenStorage->setToken(new PostAuthenticationToken($user, self::FIREWALL_NAME, $user->getRoles()));
        // The context listener of a stateful firewall would otherwise keep the token in a session.
        $request->attributes->remove('_security_firewall_run');
    }

    private function resolve(string $accessToken): ?AuthenticatedConnection
    {
        $token = $this->tokenStore->findUsableAccessToken($accessToken);
        $connection = $token?->getConnection();
        $customer = $connection?->getCustomer();
        $user = $customer instanceof CustomerInterface ? $customer->getUser() : null;

        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            return null;
        }

        if (
            null === $token || null === $connection || !$customer instanceof CustomerInterface ||
            !$user instanceof ShopUserInterface || !$user->isEnabled() ||
            !$channel instanceof ChannelInterface || $connection->getChannelCode() !== $channel->getCode()
        ) {
            return null;
        }

        return new AuthenticatedConnection($connection, $user, $customer, $channel, $token->getScopes());
    }

    private function touch(AuthenticatedConnection $connection): void
    {
        $now = $this->tokenStore->now();
        $lastUsedAt = $connection->connection->getLastUsedAt();
        if (null === $lastUsedAt || $lastUsedAt < $now->sub(new \DateInterval(self::LAST_USED_RESOLUTION))) {
            $connection->connection->setLastUsedAt($now);
            $this->entityManager->flush();
        }
    }

    private function unauthorized(Request $request, ?string $error): Response
    {
        $challenge = \sprintf('Bearer resource_metadata="%s", scope="%s"', $this->endpoint->getResourceMetadataUrl($request), implode(' ', Scopes::ALL));
        if (null !== $error) {
            $challenge .= \sprintf(', error="%s", error_description="The access token is invalid, expired or revoked."', $error);
        }

        return new JsonResponse(
            ['error' => $error ?? 'unauthorized', 'error_description' => 'Connect this assistant to your shop account (OAuth 2.1).'],
            Response::HTTP_UNAUTHORIZED,
            ['WWW-Authenticate' => $challenge, 'Cache-Control' => 'no-store'],
        );
    }
}
