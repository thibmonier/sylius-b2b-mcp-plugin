<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\RateLimit;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/** The limiters of the plugin (framework.rate_limiter, prepended by the extension). */
final class RateLimiter
{
    public function __construct(
        private readonly RateLimiterFactory $mcpRequests,
        private readonly RateLimiterFactory $oauthRequests,
        private readonly RateLimiterFactory $clientRegistrations,
    ) {
    }

    /** Null when accepted, else a 429 response. */
    public function consumeMcpRequest(string $key): ?Response
    {
        return $this->consume($this->mcpRequests, $key);
    }

    public function consumeOAuthRequest(?string $ip): ?Response
    {
        return $this->consume($this->oauthRequests, self::ipKey($ip));
    }

    public function consumeClientRegistration(?string $ip): ?Response
    {
        return $this->consume($this->clientRegistrations, self::ipKey($ip));
    }

    /** IPv4, or the /56 of an IPv6 address (one subscriber commonly gets a whole /56). */
    public static function ipKey(?string $ip): string
    {
        if (null === $ip || '' === $ip) {
            return 'unknown';
        }
        $packed = @inet_pton($ip);
        if (false === $packed || 16 !== \strlen($packed)) {
            return $ip;
        }
        if (str_starts_with($packed, str_repeat("\0", 10) . "\xff\xff")) {
            return (string) inet_ntop(substr($packed, 12));
        }

        return (string) inet_ntop(substr($packed, 0, 7) . str_repeat("\0", 9)) . '/56';
    }

    private function consume(RateLimiterFactory $factory, string $key): ?Response
    {
        $limit = $factory->create($key)->consume();
        if ($limit->isAccepted()) {
            return null;
        }

        $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());

        return new JsonResponse(
            ['error' => 'too_many_requests', 'error_description' => 'Too many requests. Retry later.'],
            Response::HTTP_TOO_MANY_REQUESTS,
            ['Retry-After' => (string) $retryAfter],
        );
    }
}
